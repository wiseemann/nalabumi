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
        $totalArticles = Article::where('is_analyzed', true)
            ->whereHas('cases.location', function ($q) {
                $q->validLocation();
            })->count();
        $totalCases = EnvironmentalCase::whereHas('article', function ($q) {
            $q->where('is_analyzed', true);
        })->whereHas('location', function ($q) {
            $q->validLocation();
        })->count();
        
        $totalLocations = Location::countNormalizedLocationsWithCases();

        $casesByCategory = Category::withCount(['cases' => function ($query) {
            $query->whereHas('article', function ($q) {
                $q->where('is_analyzed', true);
            })->whereHas('location', function ($q) {
                $q->validLocation();
            });
        }])
            ->orderByDesc('cases_count')
            ->get();

        $allLocations = Location::validLocation()
            ->withCount('cases')
            ->with(['latestUrgencyScore'])
            ->having('cases_count', '>', 0)
            ->get();

        $topRegions = $allLocations
            ->groupBy(function ($loc) {
                if (!empty($loc->regency_name)) {
                    $name = $loc->regency_name;
                } else if (!empty($loc->province_name) && $loc->province_name !== '-') {
                    $name = $loc->province_name;
                } else {
                    $name = $loc->name;
                }
                return strtolower(trim(preg_replace('/^(?:Kabupaten|Kota Administrasi|Kota|Kecamatan)\s+/i', '', $name ?? '')));
            })
            ->map(function ($group) {
                $representative = $group->sortByDesc('cases_count')->first();
                $totalCases = $group->sum('cases_count');
                
                // Get the highest urgency score among the group
                $bestScore = $group->pluck('latestUrgencyScore')->filter()->sortByDesc('score')->first();

                // Override the cases_count on the representative
                $representative->cases_count = $totalCases;
                // Assign the best score to the representative
                if ($bestScore) {
                    $representative->setRelation('latestUrgencyScore', $bestScore);
                }

                return $representative;
            })
            ->sortByDesc('cases_count')
            ->values(); // Return ALL, no limit(10)

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
