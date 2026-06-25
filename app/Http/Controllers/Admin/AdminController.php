<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeArticleJob;
use App\Jobs\ScrapeArticleJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\EnvironmentalCase;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

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
            'unscraped' => Article::where('is_scraped', false)->count(),
            'unanalyzed' => Article::where('is_scraped', true)->where('is_analyzed', false)->count(),
            'totalCases' => EnvironmentalCase::count(),
            'totalLocations' => Location::has('cases')->count(),
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
     * Store a new custom RSS feed and fetch its articles immediately.
     */
    public function storeRss(Request $request, \App\Services\RssFeedService $rssFeedService)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:500|unique:rss_feeds,url',
            'source' => 'required|string|max:255',
        ]);

        $feed = \App\Models\RssFeed::create([
            'url' => $validated['url'],
            'source' => $validated['source'],
        ]);

        try {
            // Fetch and store articles from this feed immediately
            $storedArticles = $rssFeedService->fetchAndStoreSingle($feed->url, $feed->source);
            
            // Dispatch scraping for newly stored articles
            foreach ($storedArticles as $article) {
                \App\Jobs\ScrapeArticleJob::dispatch($article);
            }
            
            $count = count($storedArticles);
            return back()->with('success', "RSS Feed berhasil ditambahkan. Terbaca {$count} artikel baru yang akan di-scrape & dianalisis secara otomatis.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to parse manual RSS feed: " . $e->getMessage());
            return back()->with('success', "RSS Feed berhasil ditambahkan, tetapi gagal mengambil artikel saat ini: " . $e->getMessage());
        }
    }

    /**
     * Generate or regenerate embeddings for all analyzed articles.
     */
    public function generateEmbeddings()
    {
        $articles = Article::where('is_analyzed', true)->get();
        $count = $articles->count();

        foreach ($articles as $article) {
            \App\Jobs\GenerateEmbeddingJob::dispatch($article);
        }

        return back()->with('success', "Pembuatan embedding berhasil dipicu untuk {$count} artikel.");
    }

    /**
     * Check and delete duplicate articles.
     */
    public function deleteDuplicates()
    {
        $deletedCount = 0;
        
        // 1. Group by title and delete duplicates
        $duplicateTitles = Article::select('title')
            ->groupBy('title')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('title');

        foreach ($duplicateTitles as $title) {
            $articles = Article::where('title', $title)->orderBy('id')->get();
            $keep = $articles->first();
            $duplicates = $articles->slice(1);

            foreach ($duplicates as $duplicate) {
                $duplicate->categories()->detach();
                $duplicate->locations()->detach();
                $duplicate->cases()->delete();
                app(\App\Services\QdrantService::class)->deletePoint($duplicate->id);
                $duplicate->delete();
                $deletedCount++;
            }
        }

        // 2. Group by URL and delete duplicates
        $duplicateUrls = Article::select('url')
            ->groupBy('url')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('url');

        foreach ($duplicateUrls as $url) {
            $articles = Article::where('url', $url)->orderBy('id')->get();
            $keep = $articles->first();
            $duplicates = $articles->slice(1);

            foreach ($duplicates as $duplicate) {
                $duplicate->categories()->detach();
                $duplicate->locations()->detach();
                $duplicate->cases()->delete();
                app(\App\Services\QdrantService::class)->deletePoint($duplicate->id);
                $duplicate->delete();
                $deletedCount++;
            }
        }

        return back()->with('success', "Berhasil menghapus {$deletedCount} artikel duplikat.");
    }
}
