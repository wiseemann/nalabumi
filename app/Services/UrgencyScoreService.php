<?php

namespace App\Services;

use App\Models\Location;
use App\Models\UrgencyScore;
use Illuminate\Support\Facades\DB;

class UrgencyScoreService
{
    public function calculateUrgencyScores(?\Carbon\Carbon $date = null): void
    {
        $date = $date ?? now()->toDateString();

        $locations = Location::has('cases')->withCount('cases')->get();

        foreach ($locations as $location) {
            $score = $this->calculateLocationScore($location, $date);
            $this->storeUrgencyScore($location, $score, $date);
        }
    }

    protected function calculateLocationScore(Location $location, string $date): array
    {
        $cases = $location->cases()
            ->whereHas('article', function ($query) use ($date) {
                $query->whereDate('created_at', '<=', $date);
            })
            ->get();

        $caseCount = $cases->count();

        if ($caseCount === 0) {
            return [
                'score' => 0,
                'level' => 'rendah',
                'case_count' => 0,
                'avg_severity' => 0,
            ];
        }

        $avgSeverity = $cases->avg('severity') ?? 0;

        $recencyWeight = $this->calculateRecencyWeight($cases, $date);
        $categoryDiversity = $this->calculateCategoryDiversity($cases);

        $baseScore = ($avgSeverity * 0.4) + ($caseCount * 5 * 0.3) + ($recencyWeight * 100 * 0.2) + ($categoryDiversity * 100 * 0.1);
        $finalScore = min(100, max(0, (int) $baseScore));

        $level = $this->scoreToLevel($finalScore);

        return [
            'score' => $finalScore,
            'level' => $level,
            'case_count' => $caseCount,
            'avg_severity' => (int) $avgSeverity,
        ];
    }

    protected function calculateRecencyWeight($cases, string $date): float
    {
        $dateObj = \Carbon\Carbon::parse($date);
        $totalWeight = 0;
        $caseCount = $cases->count();

        foreach ($cases as $case) {
            $daysDiff = $dateObj->diffInDays($case->created_at);
            $weight = max(0, 1 - ($daysDiff / 365));
            $totalWeight += $weight;
        }

        return $caseCount > 0 ? $totalWeight / $caseCount : 0;
    }

    protected function calculateCategoryDiversity($cases): float
    {
        $uniqueCategories = $cases->pluck('category_id')->unique()->count();
        $totalCategories = 8;

        return min(1, $uniqueCategories / ($totalCategories * 0.5));
    }

    protected function scoreToLevel(int $score): string
    {
        return match(true) {
            $score >= 61 => 'kritis',
            $score >= 41 => 'tinggi',
            $score >= 21 => 'sedang',
            default => 'rendah',
        };
    }

    protected function storeUrgencyScore(Location $location, array $score, string $date): void
    {
        UrgencyScore::updateOrCreate(
            [
                'location_id' => $location->id,
                'date' => $date,
            ],
            $score
        );
    }

    public function getLatestScores(): array
    {
        return UrgencyScore::select('urgency_scores.*')
            ->join(
                DB::raw('(SELECT location_id, MAX(date) as max_date FROM urgency_scores GROUP BY location_id) as latest'),
                function ($join) {
                    $join->on('urgency_scores.location_id', '=', 'latest.location_id')
                        ->on('urgency_scores.date', '=', 'latest.max_date');
                }
            )
            ->with('location')
            ->orderByDesc('score')
            ->get();
    }

    public function getScoresForDate(string $date): array
    {
        return UrgencyScore::where('date', $date)
            ->with('location')
            ->orderByDesc('score')
            ->get();
    }
}
