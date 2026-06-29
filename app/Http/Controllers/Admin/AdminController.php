<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeArticleJob;
use App\Jobs\CalculateReliabilityJob;
use App\Jobs\GenerateEmbeddingJob;
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
use Illuminate\Support\Facades\Redis;
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
            'pending' => $query->whereNull('is_verified')->where('is_analyzed', true),
            'verified' => $query->where('is_verified', true),
            'rejected' => $query->where('is_verified', false),
            'unanalyzed' => $query->where('is_scraped', true)->where('is_analyzed', false),
            'scrape_failed' => $query->where('scrape_failed', true),
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
            'scrape_failed' => $article->scrape_failed,
            'is_analyzed' => $article->is_analyzed,
            'has_embedding' => $article->has_embedding,
            'is_verified' => $article->is_verified,
            'verified_at' => $article->verified_at?->format('d M Y, H:i'),
            'categories' => $article->categories->pluck('name'),
            'locations' => $article->locations->map(fn($l) => $l->regency_name ?? $l->name),
            'severity' => $article->cases->first()?->severity,
            'confidence' => $article->cases->first()?->confidence,
            'cause' => $article->cases->first()?->cause,
            'impact' => $article->cases->first()?->impact,
            'reliability_score' => $article->reliability_score,
            'reliability_level' => $article->reliability_level,
            'scraped_at' => $article->scraped_at?->format('d M Y, H:i'),
            'analyzed_at' => $article->analyzed_at?->format('d M Y, H:i'),
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
            'no_embedding' => Article::where('is_analyzed', true)->where('has_embedding', false)->count(),
            'no_reliability' => Article::where('is_analyzed', true)->whereNull('reliability_score')->count(),
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
     * Delete an article.
     */
    public function destroy(int $id)
    {
        $article = Article::findOrFail($id);
        $title = $article->title;
        $article->delete();

        return back()->with('success', "Artikel \"{$title}\" telah dihapus.");
    }

    /**
     * Bulk verify articles.
     */
    public function bulkVerify(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:articles,id'])['ids'];
        Article::whereIn('id', $ids)->update([
            'is_verified' => true,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', count($ids) . ' artikel telah diverifikasi.');
    }

    /**
     * Bulk reject articles.
     */
    public function bulkReject(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:articles,id'])['ids'];
        Article::whereIn('id', $ids)->update([
            'is_verified' => false,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', count($ids) . ' artikel telah ditolak.');
    }

    /**
     * Bulk delete articles.
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:articles,id'])['ids'];
        Article::whereIn('id', $ids)->delete();

        return back()->with('success', count($ids) . ' artikel telah dihapus.');
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
     * Dispatch embedding jobs for all analyzed articles without embeddings.
     */
    public function runEmbedding()
    {
        $articles = Article::where('is_analyzed', true)->where('has_embedding', false)->get();
        $count = $articles->count();

        if ($count === 0) {
            return back()->with('info', 'Tidak ada artikel yang perlu diproses embedding.');
        }

        foreach ($articles as $article) {
            GenerateEmbeddingJob::dispatch($article);
        }

        return back()->with('success', "{$count} artikel dikirim ke antrean embedding.");
    }

    /**
     * Dispatch reliability calculation jobs for all analyzed articles without scores.
     */
    public function runReliability()
    {
        $articles = Article::where('is_analyzed', true)->whereNull('reliability_score')->get();
        $count = $articles->count();

        if ($count === 0) {
            return back()->with('info', 'Tidak ada artikel yang perlu dihitung reliabilitasnya.');
        }

        foreach ($articles as $article) {
            CalculateReliabilityJob::dispatch($article);
        }

        return back()->with('success', "{$count} artikel dikirim ke antrean reliabilitas.");
    }

    /**
     * View queue status (all job types).
     */
    public function scrapeQueue(Request $request)
    {
        $type = $request->get('type', 'all');
        $queueKey = 'queues:default';

        // Get all pending jobs from Redis
        $rawJobs = Redis::lrange($queueKey, 0, -1) ?: [];
        $allJobs = collect($rawJobs)
            ->map(fn($raw) => $this->mapRedisJob($raw))
            ->filter();

        $pending = $allJobs->count();
        $counts = $allJobs->groupBy('job_type')->map->count();

        $recentJobs = $allJobs->sortByDesc('created_at')->values()->take(50);

        $filtered = match ($type) {
            'all' => $recentJobs,
            default => $recentJobs->where('job_type', $type)->values(),
        };

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
            'pending' => $pending,
            'counts' => $counts,
            'failed' => DB::table('failed_jobs')->count(),
            'recentJobs' => $filtered,
            'failedJobs' => $failedJobs,
            'type' => $type,
        ]);
    }

    private function mapRedisJob(?string $raw): ?array
    {
        if (!$raw) return null;
        $payload = json_decode($raw, true);
        if (!$payload) return null;

        $displayName = $payload['displayName'] ?? class_basename($payload['data']['commandName'] ?? 'Unknown');

        $jobType = match (true) {
            str_contains($displayName, 'ScrapeUnscraped') => 'scrape_unscraped',
            str_contains($displayName, 'ScrapeArticle') => 'scrape',
            str_contains($displayName, 'AnalyzeArticle') => 'analyze',
            str_contains($displayName, 'GenerateEmbedding') => 'embedding',
            str_contains($displayName, 'CalculateReliability') => 'reliability',
            default => 'other',
        };

        return [
            'id' => $payload['id'] ?? null,
            'display_name' => $displayName,
            'job_type' => $jobType,
            'attempts' => $payload['attempts'] ?? 0,
            'available_at' => isset($payload['availableAt']) ? date('d M Y, H:i', $payload['availableAt']) : '—',
            'created_at' => isset($payload['createdAt']) ? date('d M Y, H:i', $payload['createdAt']) : '—',
        ];
    }
}
