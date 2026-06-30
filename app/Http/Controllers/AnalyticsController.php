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

        $jakartaMunicipalities = [
            'Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur',
        ];

        $topRegions = $allLocations
            ->groupBy(function ($loc) {
                if (!empty($loc->regency_name)) {
                    $name = $loc->regency_name;
                } else if (!empty($loc->province_name) && $loc->province_name !== '-') {
                    $name = $loc->province_name;
                } else {
                    $name = $loc->name;
                }
                return \App\Models\Location::normalizeName($name);
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
            ->flatMap(function ($representative, $normalizedKey) use ($jakartaMunicipalities) {
                // Expand generic "jakarta" / "kota jakarta" into 5 specific municipalities
                if (in_array($normalizedKey, ['jakarta', 'kota jakarta'], true)) {
                    return collect($jakartaMunicipalities)->map(function ($municipality) use ($representative) {
                        $clone = clone $representative;
                        $clone->regency_name = $municipality;
                        return $clone;
                    });
                }
                return [$representative];
            })
            ->sortByDesc('cases_count')
            ->values();

        // Bug 4: Group by article's original publish date (not scrape/created date)
        $dailyTrend = EnvironmentalCase::join('articles', 'cases.article_id', '=', 'articles.id')
            ->selectRaw('DATE(articles.published_at) as date, COUNT(*) as count')
            ->whereNotNull('articles.published_at')
            ->groupBy('date')
            ->orderBy('date')
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
