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
        $period = $request->get('period', '30');
        $region = $request->get('region', 'all');

        $dateFrom = now()->subDays($period)->toDateString();

        $totalReports = EnvironmentalCase::whereHas('article', function ($q) use ($dateFrom) {
            $q->where('created_at', '>=', $dateFrom);
        })->count();

        $criticalCount = EnvironmentalCase::where('severity', '>=', 61)
            ->whereHas('article', function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom);
            })->count();

        $affectedAreas = Location::has('cases')->count();

        $monthlyTrend = EnvironmentalCase::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->whereHas('article', function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom);
            })
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get()
            ->map(function ($item) {
                return [
                    'month' => date('M', strtotime($item->month . '-01')),
                    'count' => $item->count,
                ];
            });

        $categoryBreakdown = Category::withCount(['cases' => function ($query) use ($dateFrom) {
            $query->whereHas('article', function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom);
            });
        }])
            ->orderByDesc('cases_count')
            ->get();

        $topEntities = EnvironmentalCase::selectRaw('locations.regency_name, locations.province_name, categories.name as category_name, COUNT(*) as report_count, AVG(cases.severity) as avg_severity')
            ->join('locations', 'cases.location_id', '=', 'locations.id')
            ->join('categories', 'cases.category_id', '=', 'categories.id')
            ->whereHas('article', function ($q) use ($dateFrom) {
                $q->where('created_at', '>=', $dateFrom);
            })
            ->groupBy('locations.regency_name', 'locations.province_name', 'categories.name')
            ->orderByDesc('report_count')
            ->limit(10)
            ->get();

        return Inertia::render('Analytics', [
            'filters' => [
                'period' => $period,
                'region' => $region,
            ],
            'stats' => [
                'totalReports' => $totalReports,
                'criticalCount' => $criticalCount,
                'affectedAreas' => $affectedAreas,
            ],
            'monthlyTrend' => $monthlyTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'topEntities' => $topEntities,
        ]);
    }
}
