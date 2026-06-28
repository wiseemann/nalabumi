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
        $totalArticles = Article::where('is_analyzed', true)->count();
        $totalCases = EnvironmentalCase::whereHas('article', function ($q) {
            $q->where('is_analyzed', true);
        })->count();
        
        $totalLocations = Location::countNormalizedLocationsWithCases();

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

        $dailyTrend = EnvironmentalCase::selectRaw('DATE(created_at) as date, COUNT(*) as count')
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
