<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeArticleJob;
use App\Jobs\ScrapeArticleJob;
use App\Jobs\ScrapeUnscrapedArticlesJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\EnvironmentalCase;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\RssFeedService;

class AdminController extends Controller
{
    /**
     * Admin dashboard with overview stats and article management.
     */
    public function dashboard(Request $request)
    {
        $filter = $request->get('filter', 'all'); // all, pending, verified, rejected
        $search = $request->get('search');

        $query = Article::with(['categories', 'locations', 'cases'])
            ->orderByDesc('created_at');

        // Apply filter
        match ($filter) {
            'pending' => $query->whereNull('is_verified'),
            'verified' => $query->where('is_verified', true),
            'rejected' => $query->where('is_verified', false),
            default => null,
        };

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%");
            });
        }

        $articles = $query->paginate(20)->through(fn($article) => [
            'id' => $article->id,
            'title' => $article->title,
            'source' => $article->source,
            'url' => $article->url,
            'published_at' => $article->published_at?->format('d M Y, H:i'),
            'is_scraped' => $article->is_scraped,
            'is_analyzed' => $article->is_analyzed,
            'is_verified' => $article->is_verified,
            'verified_at' => $article->verified_at?->format('d M Y, H:i'),
            'categories' => $article->categories->pluck('name'),
            'locations' => $article->locations->map(fn($l) => $l->regency_name ?? $l->name),
            'severity' => $article->cases->first()?->severity,
            'confidence' => $article->cases->first()?->confidence,
        ]);

        // Overview stats
        $stats = [
            'total' => Article::count(),
            'pending' => Article::whereNull('is_verified')->where('is_analyzed', true)->count(),
            'verified' => Article::where('is_verified', true)->count(),
            'rejected' => Article::where('is_verified', false)->count(),
            'unscraped' => Article::where('is_scraped', false)->where('scrape_failed', false)->count(),
            'scrape_failed' => Article::where('scrape_failed', true)->count(),
            'unanalyzed' => Article::where('is_scraped', true)->where('is_analyzed', false)->count(),
            'totalCases' => EnvironmentalCase::count(),
            'totalLocations' => Location::countNormalizedLocationsWithCases(),
        ];

        $categories = Category::all();

        return Inertia::render('Admin/AdminDashboard', [
            'articles' => $articles,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
            ],
            'isBingLocked' => \Illuminate\Support\Facades\Cache::has('bing_historical_lock'),
        ]);
    }

    /**
     * Verify an article as valid.
     */
    public function verify(int $id)
    {
        $article = Article::findOrFail($id);
        $article->update([
            'is_verified' => true,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', "Artikel \"{$article->title}\" telah diverifikasi.");
    }

    /**
     * Reject an article as invalid.
     */
    public function reject(int $id)
    {
        $article = Article::findOrFail($id);
        $article->update([
            'is_verified' => false,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', "Artikel \"{$article->title}\" telah ditolak.");
    }

    /**
     * Create an article manually.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'url' => 'required|url|max:2000|unique:articles,url',
            'source' => 'required|string|max:255',
            'content' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);

        $article = Article::create([
            'title' => $validated['title'],
            'url' => $validated['url'],
            'source' => $validated['source'],
            'content' => $validated['content'] ?? null,
            'published_at' => $validated['published_at'] ?? now(),
            'is_verified' => true,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        // If no content provided, dispatch scrape job
        if (empty($validated['content'])) {
            ScrapeArticleJob::dispatch($article);
            return back()->with('success', "Artikel berhasil ditambahkan. Konten akan di-scrape secara otomatis.");
        }

        // If content provided, go straight to analysis
        $article->update(['is_scraped' => true, 'scraped_at' => now()]);
        AnalyzeArticleJob::dispatch($article);

        return back()->with('success', "Artikel berhasil ditambahkan dan akan dianalisis.");
    }

    /**
     * Dispatch AnalyzeArticleJob for all scraped but unanalyzed articles.
     */
    public function syncUnanalyzed()
    {
        $articles = Article::where('is_scraped', true)->where('is_analyzed', false)->get();
        
        foreach ($articles as $article) {
            AnalyzeArticleJob::dispatch($article);
        }

        return back()->with('success', "{$articles->count()} artikel telah dimasukkan ke antrean analisis ulang.");
    }

    /**
     * Fetch historical articles from Google News RSS.
     */
    public function fetchHistorical(RssFeedService $rssFeedService)
    {
        if (\Illuminate\Support\Facades\Cache::has('bing_historical_lock')) {
            return back()->with('error', 'Penarikan riwayat massal (Bing) sedang dalam masa tunggu (Cooldown). Harap tunggu beberapa jam sebelum menggunakan fitur ini lagi.');
        }

        $days = 15;
        $count = $rssFeedService->fetchHistoricalFromGoogle($days);

        // Kunci tombol selama 12 jam ke depan (43200 detik) untuk keamanan anti-spam
        \Illuminate\Support\Facades\Cache::put('bing_historical_lock', true, now()->addHours(12));

        return back()->with('success', "Berhasil menarik {$count} artikel historis dari {$days} hari terakhir. Artikel sedang diproses di latar belakang.");
    }

    /**
     * Dispatch a single job to scrape all unscraped articles in the background.
     */
    public function runScrape()
    {
        $totalUnscraped = Article::where('is_scraped', false)->whereNotNull('url')->count();
        $newAttempts = Article::where('is_scraped', false)->where('scrape_failed', false)->whereNotNull('url')->count();
        $retries = Article::where('scrape_failed', true)->whereNotNull('url')->count();

        if ($totalUnscraped === 0) {
            return back()->with('info', 'Tidak ada artikel yang perlu di-scrape.');
        }

        if (Cache::has('scrape_lock')) {
            return back()->with('error', 'Scrape sedang berjalan. Harap tunggu beberapa saat.');
        }

        Cache::put('scrape_lock', true, now()->addMinutes(10));

        ScrapeUnscrapedArticlesJob::dispatch();

        return back()->with('success', "{$totalUnscraped} artikel dikirim: {$newAttempts} baru, {$retries} ulang.");
    }

    /**
     * View queue status (scrape + analysis jobs).
     */
    public function scrapeQueue(Request $request)
    {
        $type = $request->get('type', 'all');

        $query = DB::table('jobs')->where('queue', 'default');

        $recentJobs = (clone $query)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $displayName = $payload['displayName'] ?? class_basename($payload['data']['commandName'] ?? 'Unknown');

                $category = match (true) {
                    str_contains($displayName, 'Scrape') => 'scrape',
                    str_contains($displayName, 'Analyze') => 'analysis',
                    str_contains($displayName, 'GenerateEmbedding') => 'analysis',
                    str_contains($displayName, 'CalculateReliability') => 'analysis',
                    default => 'other',
                };

                return [
                    'id' => $job->id,
                    'display_name' => $displayName,
                    'category' => $category,
                    'attempts' => $job->attempts,
                    'available_at' => date('d M Y, H:i', $job->available_at),
                    'created_at' => date('d M Y, H:i', $job->created_at),
                ];
            });

        $filtered = match ($type) {
            'scrape' => $recentJobs->where('category', 'scrape')->values(),
            'analysis' => $recentJobs->where('category', 'analysis')->values(),
            default => $recentJobs,
        };

        // Count by category from all pending jobs
        $allPending = (clone $query)->orderByDesc('created_at')->get()->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $displayName = $payload['displayName'] ?? class_basename($payload['data']['commandName'] ?? 'Unknown');
            return match (true) {
                str_contains($displayName, 'Scrape') => 'scrape',
                str_contains($displayName, 'Analyze') => 'analysis',
                str_contains($displayName, 'GenerateEmbedding') => 'analysis',
                str_contains($displayName, 'CalculateReliability') => 'analysis',
                default => 'other',
            };
        });

        $totalScrape = $allPending->filter(fn($c) => $c === 'scrape')->count();
        $totalAnalysis = $allPending->filter(fn($c) => $c === 'analysis')->count();

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(20)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                    'exception_preview' => mb_substr($job->exception, 0, 200),
                ];
            });

        return response()->json([
            'pending' => $query->count(),
            'pending_scrape' => $totalScrape,
            'pending_analysis' => $totalAnalysis,
            'failed' => DB::table('failed_jobs')->count(),
            'recentJobs' => $filtered,
            'failedJobs' => $failedJobs,
            'type' => $type,
        ]);
    }
}
