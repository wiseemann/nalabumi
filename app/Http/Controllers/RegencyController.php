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

        // Find location by regency name (partial match)
        $location = Location::where('regency_name', 'like', "%{$regencyName}%")
            ->orWhere('name', 'like', "%{$regencyName}%")
            ->first();

        if (!$location) {
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

        // Load the latest urgency score for this location
        $urgencyScore = $location->latestUrgencyScore;

        // Build case query with filters
        $query = EnvironmentalCase::with(['article', 'category', 'location'])
            ->where('location_id', $location->id);

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
                'cause' => $case->cause ?? null,
                'impact' => $case->impact ?? null,
                'location_name' => $location->regency_name ?? $location->name,
                'province_name' => $location->province_name,
            ]);

        // Stats grouped by severity level using DB queries (more efficient)
        $stats = [
            'critical' => EnvironmentalCase::where('location_id', $location->id)->where('severity', '>=', 61)->count(),
            'high'     => EnvironmentalCase::where('location_id', $location->id)->whereBetween('severity', [41, 60])->count(),
            'medium'   => EnvironmentalCase::where('location_id', $location->id)->whereBetween('severity', [21, 40])->count(),
            'low'      => EnvironmentalCase::where('location_id', $location->id)->where('severity', '<=', 20)->count(),
            'total'    => EnvironmentalCase::where('location_id', $location->id)->count(),
        ];

        $categories = Category::all();

        return Inertia::render('RegencyDetail', [
            'regency' => $location->regency_name ?? $location->name,
            'province' => $location->province_name ?? $request->get('province', ''),
            'location' => $location,
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
