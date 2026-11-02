<?php

namespace App\Http\Controllers;

use App\Models\EnvironmentalCase;
use App\Models\Location;
use App\Models\UrgencyScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /**
     * Return urgency scores for the choropleth map with optional category and date filtering.
     */
    public function urgencyScores(Request $request)
    {
        $categoryId = $request->get('category');
        $days = $request->get('days');

        $version = Cache::get('urgency_scores_version', 1);
        $cacheKey = "urgency_scores:v{$version}:" . md5(json_encode([
            'category' => $categoryId,
            'days' => $days,
        ]));

        $scores = Cache::remember($cacheKey, 1800, function () use ($categoryId, $days) {
            // Default: All categories & All / standard 30-day snapshot -> use precomputed urgency_scores table
            if (empty($categoryId) && (empty($days) || $days === 'all' || $days === '30')) {
                return UrgencyScore::select('urgency_scores.*')
                    ->join(
                        DB::raw('(SELECT location_id, MAX(date) as max_date FROM urgency_scores GROUP BY location_id) as latest'),
                        function ($join) {
                            $join->on('urgency_scores.location_id', '=', 'latest.location_id')
                                ->on('urgency_scores.date', '=', 'latest.max_date');
                        }
                    )
                    ->with('location')
                    ->whereHas('location', function ($q) {
                        $q->validLocation();
                    })
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
            }

            // Filtered query: dynamically aggregate cases based on category and/or date cutoff
            $caseQuery = EnvironmentalCase::query()
                ->select(
                    'cases.location_id',
                    DB::raw('COUNT(cases.id) as case_count'),
                    DB::raw('AVG(cases.severity) as avg_severity')
                )
                ->whereHas('location', function ($q) {
                    $q->validLocation();
                });

            if (!empty($categoryId)) {
                $caseQuery->where('cases.category_id', $categoryId);
            }

            if (!empty($days) && is_numeric($days)) {
                $cutoff = now()->subDays((int) $days);
                $caseQuery->whereHas('article', function ($q) use ($cutoff) {
                    $q->where(function ($sub) use ($cutoff) {
                        $sub->whereNotNull('published_at')->where('published_at', '>=', $cutoff);
                    })->orWhere(function ($sub) use ($cutoff) {
                        $sub->whereNull('published_at')->where('created_at', '>=', $cutoff);
                    });
                });
            }

            $casesAgg = $caseQuery->groupBy('cases.location_id')
                ->with('location')
                ->get();

            return $casesAgg->map(function ($row) {
                $count = (int) $row->case_count;
                $avgSev = (float) $row->avg_severity;

                // Urgency calculation: volume multiplier penalizes locations with very few cases
                $volMult = min(1.0, 0.5 + ($count * 0.1));
                $rawScore = min(100, max(0, (int) round($avgSev * $volMult)));

                $level = match (true) {
                    $rawScore >= 61 => 'kritis',
                    $rawScore >= 41 => 'tinggi',
                    $rawScore >= 21 => 'sedang',
                    default => 'rendah',
                };

                $hexColors = [
                    'rendah' => '#22c55e',
                    'sedang' => '#eab308',
                    'tinggi' => '#f97316',
                    'kritis' => '#ef4444',
                ];

                return [
                    'id' => $row->location_id,
                    'location_id' => $row->location_id,
                    'regency_name' => $row->location->regency_name ?? $row->location->name,
                    'province_name' => $row->location->province_name,
                    'geojson_id' => $row->location->geojson_id,
                    'latitude' => $row->location->latitude,
                    'longitude' => $row->location->longitude,
                    'score' => $rawScore,
                    'level' => $level,
                    'level_color' => $hexColors[$level] ?? '#6b7280',
                    'case_count' => $count,
                    'avg_severity' => (int) round($avgSev),
                    'date' => now()->toDateString(),
                ];
            });
        });

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
