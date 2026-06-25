<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScrapeArticleJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $timeout = 120;

    public function __construct(
        public Article $article
    ) {}

    public function handle(ArticleScraperService $scraper): void
    {
        $success = $scraper->scrapeArticle($this->article);

        if ($success) {
            AnalyzeArticleJob::dispatch($this->article);
        }
    }
}
