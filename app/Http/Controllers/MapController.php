<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\UrgencyScore;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /**
     * Return urgency scores for the choropleth map.
     */
    public function urgencyScores()
    {
        $scores = UrgencyScore::select('urgency_scores.*')
            ->join(
                DB::raw('(SELECT location_id, MAX(date) as max_date FROM urgency_scores GROUP BY location_id) as latest'),
                function ($join) {
                    $join->on('urgency_scores.location_id', '=', 'latest.location_id')
                        ->on('urgency_scores.date', '=', 'latest.max_date');
                }
            )
            ->with('location')
            ->orderByDesc('score')
            ->get()
            ->map(fn($score) => [
                'id' => $score->id,
                'location_id' => $score->location_id,
                'regency_name' => $score->location->regency_name ?? $score->location->name,
                'province_name' => $score->location->province_name,
                'geojson_id' => $score->location->geojson_id,
                'latitude' => $score->location->latitude,
                'longitude' => $score->location->longitude,
                'score' => $score->score,
                'level' => $score->level,
                'level_color' => $score->level_hex_color,
                'case_count' => $score->case_count,
                'avg_severity' => $score->avg_severity,
                'date' => $score->date?->format('Y-m-d'),
            ]);

        return response()->json([
            'data' => $scores,
        ]);
    }

    /**
     * Return detailed information for a specific region.
     */
    public function regionDetails(int $id)
    {
        $location = Location::with([
            'latestUrgencyScore',
            'cases' => function ($query) {
                $query->with(['article', 'category'])
                    ->orderByDesc('created_at')
                    ->limit(10);
            },
        ])->findOrFail($id);

        $urgency = $location->latestUrgencyScore;

        $cases = $location->cases->map(fn($case) => [
            'id' => $case->id,
            'title' => $case->article?->title ?? '—',
            'source' => $case->article?->source ?? '—',
            'url' => $case->article?->url,
            'category' => $case->category?->name ?? '—',
            'severity' => $case->severity,
            'confidence' => $case->confidence ?? 0,
            'cause' => $case->cause,
            'impact' => $case->impact,
            'date' => $case->article?->published_at?->format('d M Y'),
        ]);

        return response()->json([
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'regency_name' => $location->regency_name,
                'province_name' => $location->province_name,
            ],
            'urgency' => $urgency ? [
                'score' => $urgency->score,
                'level' => $urgency->level,
                'level_color' => $urgency->level_hex_color,
                'case_count' => $urgency->case_count,
                'avg_severity' => $urgency->avg_severity,
            ] : null,
            'cases' => $cases,
        ]);
    }
}
