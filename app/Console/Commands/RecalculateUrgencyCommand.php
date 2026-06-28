<?php

namespace App\Console\Commands;

use App\Services\UrgencyScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecalculateUrgencyCommand extends Command
{
    protected $signature = 'nalabumi:recalculate-urgency';
    protected $description = 'Recalculate urgency scores for all locations';

    public function handle(UrgencyScoreService $urgencyService): int
    {
        $this->info('Recalculating urgency scores...');

        $urgencyService->calculateUrgencyScores();
        Cache::forget('dashboard_urgency_scores');

        $this->info('Urgency scores recalculated successfully');

        return Command::SUCCESS;
    }
}
