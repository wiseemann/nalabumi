<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\UrgencyScore;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MapController extends Controller
{
    public function index()
    {
        $categories = Category::all();

        return Inertia::render('Map', [
            'categories' => $categories,
        ]);
    }

    public function urgencyScores(Request $request)
    {
        $query = UrgencyScore::select('urgency_scores.*')
            ->join(
                \DB::raw('(SELECT location_id, MAX(date) as max_date FROM urgency_scores GROUP BY location_id) as latest'),
                function ($join) {
                    $join->on('urgency_scores.location_id', '=', 'latest.location_id')
                        ->on('urgency_scores.date', '=', 'latest.max_date');
                }
            )
            ->with('location');

        if ($request->filled('category')) {
            $query->whereHas('location.cases', function ($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $scores = $query->get();

        return response()->json($scores);
    }

    public function regionDetails($locationId)
    {
        $location = \App\Models\Location::with([
            'cases.category',
            'cases.article' => function ($query) {
                $query->select('id', 'url', 'title', 'content', 'published_at', 'source', 'author', 'image_url');
            },
            'urgencyScores' => function ($query) {
                $query->orderByDesc('date')->limit(30);
            },
        ])->findOrFail($locationId);

        return response()->json($location);
    }
}
