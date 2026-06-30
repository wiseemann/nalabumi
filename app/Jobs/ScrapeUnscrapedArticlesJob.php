<?php

namespace App\Jobs;

use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ScrapeUnscrapedArticlesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 120;
    public $tries = 1;

    public function handle(): void
    {
        $articles = Article::where('is_scraped', false)->whereNotNull('url')->get();

        foreach ($articles as $article) {
            ScrapeArticleJob::dispatch($article);
        }

        Cache::forget('scrape_lock');
    }
}
