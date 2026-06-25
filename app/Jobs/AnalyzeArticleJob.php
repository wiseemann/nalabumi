<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\AiAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeArticleJob implements ShouldQueue
{
    use Queueable;

    public $tries = 2;
    public $backoff = [120, 600];
    public $timeout = 180;

    public function __construct(
        public Article $article
    ) {}

    public function handle(AiAnalysisService $aiService): void
    {
        $result = $aiService->analyzeArticle($this->article);

        if ($result) {
            GenerateEmbeddingJob::dispatch($this->article);
        }
    }
}
