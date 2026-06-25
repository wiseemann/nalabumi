<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\EnvironmentalCase;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // Overview stats (tidak difilter periode)
        $totalArticles = Article::count();
        $totalCases = EnvironmentalCase::count();
        $totalLocations = Location::has('cases')->count();

        $casesByCategory = Category::withCount(['cases' => function ($query) {
            $query->whereHas('article', function ($q) {
                $q->where('is_analyzed', true);
            });
        }])
            ->orderByDesc('cases_count')
            ->get();

        $topRegions = Location::withCount('cases')
            ->with(['latestUrgencyScore'])
            ->orderByDesc('cases_count')
            ->limit(10)
            ->get();

        $dailyTrend = EnvironmentalCase::join('articles', 'cases.article_id', '=', 'articles.id')
            ->selectRaw('DATE(articles.published_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();

        return Inertia::render('Analytics', [
            'overview' => [
                'totalArticles' => $totalArticles,
                'totalCases' => $totalCases,
                'totalLocations' => $totalLocations,
            ],
            'casesByCategory' => $casesByCategory,
            'topRegions' => $topRegions,
            'dailyTrend' => $dailyTrend,
        ]);
    }
}
