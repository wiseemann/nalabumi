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
}
