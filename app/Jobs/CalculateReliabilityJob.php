<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleReliabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateReliabilityJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $timeout = 180;

    public function __construct(
        public Article $article
    ) {}

    public function handle(ArticleReliabilityService $reliabilityService): void
    {
        $reliabilityService->analyzeReliability($this->article);
    }
}
