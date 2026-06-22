<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::with(['categories', 'locations'])
            ->where('is_analyzed', true)
            ->orderByDesc('published_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('region')) {
            $query->whereHas('locations', function ($q) use ($request) {
                $q->where('regency_name', 'like', "%{$request->region}%")
                    ->orWhere('province_name', 'like', "%{$request->region}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('id', $request->category);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('published_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('published_at', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $statusMap = [
                'rendah' => [0, 20],
                'sedang' => [21, 40],
                'tinggi' => [41, 60],
                'kritis' => [61, 100],
            ];
            if (isset($statusMap[$request->status])) {
                $range = $statusMap[$request->status];
                $query->whereHas('cases', function ($q) use ($range) {
                    $q->whereBetween('severity', $range);
                });
            }
        }

        $articles = $query->paginate(20);

        $categories = Category::all();
        $provinces = Location::select('province_name')->distinct()->whereNotNull('province_name')->pluck('province_name');

        return Inertia::render('Archive', [
            'articles' => $articles,
            'categories' => $categories,
            'provinces' => $provinces,
            'filters' => $request->only(['search', 'region', 'category', 'date_from', 'date_to', 'status']),
        ]);
    }
}
