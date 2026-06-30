<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EnvironmentalCase;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegencyController extends Controller
{
    public function index(Request $request)
    {
        $regencyName = $request->get('regency');
        $search = $request->get('search');
        $filterCategory = $request->get('category');
        $filterDateFrom = $request->get('date_from');
        $filterDateTo = $request->get('date_to');
        $filterSeverity = $request->get('severity');
        $page = $request->get('page', 1);
        $perPage = 15;

        // Find ALL locations matching this regency name
        $locations = Location::where('regency_name', 'like', "%{$regencyName}%")
            ->orWhere('name', 'like', "%{$regencyName}%")
            ->get();

        if ($locations->isEmpty()) {
            // Return with empty data but still show the page
            return Inertia::render('RegencyDetail', [
                'regency' => $regencyName,
                'province' => $request->get('province', ''),
                'location' => null,
                'urgencyScore' => null,
                'stats' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0],
                'categories' => Category::all(),
                'cases' => [],
                'pagination' => ['total' => 0, 'perPage' => $perPage, 'currentPage' => 1, 'lastPage' => 1],
                'filters' => compact('search', 'filterCategory', 'filterDateFrom', 'filterDateTo', 'filterSeverity'),
                'from' => $request->get('from', 'dashboard'),
            ]);
        }

        $locationIds = $locations->pluck('id')->toArray();
        $primaryLocation = $locations->whereNotNull('regency_name')->first() ?? $locations->first();

        // Load the latest urgency scores for these locations and aggregate them
        $latestDate = \App\Models\UrgencyScore::whereIn('location_id', $locationIds)->max('date');
        $urgencyScore = null;

        if ($latestDate) {
            $scores = \App\Models\UrgencyScore::whereIn('location_id', $locationIds)
                ->where('date', $latestDate)
                ->get();

            if ($scores->isNotEmpty()) {
                $maxScoreObj = $scores->sortByDesc('score')->first();
                $totalCasesInRegency = \App\Models\EnvironmentalCase::whereIn('location_id', $locationIds)->count();
                $avgSeverityInRegency = \App\Models\EnvironmentalCase::whereIn('location_id', $locationIds)->avg('severity') ?? 0;

                $urgencyScore = (object)[
                    'score' => $maxScoreObj->score,
                    'level' => $maxScoreObj->level,
                    'case_count' => $totalCasesInRegency,
                    'avg_severity' => round($avgSeverityInRegency),
                    'date' => $latestDate
                ];
            }
        }

        // Build case query with filters
        $query = EnvironmentalCase::query()
            ->whereIn('location_id', $locationIds);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('article', fn($a) => $a->where('title', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%"))
                  ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($filterCategory) {
            $query->whereHas('category', fn($c) => $c->where('name', $filterCategory));
        }

        if ($filterDateFrom) {
            $query->whereHas('article', fn($a) => $a->where('published_at', '>=', $filterDateFrom));
        }

        if ($filterDateTo) {
            $query->whereHas('article', fn($a) => $a->where('published_at', '<=', $filterDateTo));
        }

        if ($filterSeverity !== null && $filterSeverity !== '') {
            if ($filterSeverity === 'kritis') {
                $query->where('severity', '>=', 61);
            } elseif ($filterSeverity === 'tinggi') {
                $query->whereBetween('severity', [41, 60]);
            } elseif ($filterSeverity === 'sedang') {
                $query->whereBetween('severity', [21, 40]);
            } elseif ($filterSeverity === 'rendah') {
                $query->where('severity', '<=', 20);
            }
        }

        $totalCases = $query->count();
        $cases = $query->with(['article', 'category'])
            ->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn($case) => [
                'id' => $case->id,
                'date' => $case->article?->published_at
                    ? $case->article->published_at->format('d M Y, H:i')
                    : ($case->created_at ? $case->created_at->format('d M Y, H:i') : '—'),
                'title' => $case->article?->title ?? '—',
                'source' => $case->article?->source ?? '—',
                'url' => $case->article?->url ?? null,
                'category' => $case->category?->name ?? '—',
                'severity' => $case->severity ?? 0,
                'confidence' => $case->confidence ?? 0,
                'cause' => $case->cause ?? null,
                'impact' => $case->impact ?? null,
                'location_name' => $case->location->regency_name ?? $case->location->name,
                'province_name' => $case->location->province_name,
            ]);

        // Stats grouped by severity level using DB queries (more efficient)
        $stats = [
            'critical' => EnvironmentalCase::whereIn('location_id', $locationIds)->where('severity', '>=', 61)->count(),
            'high'     => EnvironmentalCase::whereIn('location_id', $locationIds)->whereBetween('severity', [41, 60])->count(),
            'medium'   => EnvironmentalCase::whereIn('location_id', $locationIds)->whereBetween('severity', [21, 40])->count(),
            'low'      => EnvironmentalCase::whereIn('location_id', $locationIds)->where('severity', '<=', 20)->count(),
            'total'    => EnvironmentalCase::whereIn('location_id', $locationIds)->count(),
        ];

        $categories = Category::all();

        return Inertia::render('RegencyDetail', [
            'regency' => $primaryLocation->regency_name ?? $primaryLocation->name,
            'province' => $primaryLocation->province_name ?? $request->get('province', ''),
            'location' => $primaryLocation,
            'urgencyScore' => $urgencyScore,
            'stats' => $stats,
            'categories' => $categories,
            'cases' => $cases,
            'pagination' => [
                'total' => $totalCases,
                'perPage' => $perPage,
                'currentPage' => (int) $page,
                'lastPage' => (int) ceil($totalCases / $perPage),
            ],
            'filters' => [
                'search' => $search,
                'category' => $filterCategory,
                'date_from' => $filterDateFrom,
                'date_to' => $filterDateTo,
                'severity' => $filterSeverity,
            ],
            'from' => $request->get('from', 'dashboard'),
        ]);
    }
}
