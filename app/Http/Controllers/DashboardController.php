<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\UrgencyScore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $categories = Category::all();

        $urgencyScores = UrgencyScore::select('urgency_scores.*')
            ->join(
                DB::raw('(SELECT location_id, MAX(date) as max_date FROM urgency_scores GROUP BY location_id) as latest'),
                function ($join) {
                    $join->on('urgency_scores.location_id', '=', 'latest.location_id')
                        ->on('urgency_scores.date', '=', 'latest.max_date');
                }
            )
            ->with('location')
            ->get();

        return Inertia::render('Dashboard', [
            'categories' => $categories,
            'urgencyScores' => $urgencyScores,
        ]);
    }
}
