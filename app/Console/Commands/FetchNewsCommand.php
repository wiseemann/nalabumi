<?php

namespace App\Console\Commands;

use App\Services\RssFeedService;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature = 'nalabumi:fetch-news {--scrape : Immediately scrape articles}';
    protected $description = 'Fetch latest news from RSS feeds';

    public function handle(RssFeedService $rssFeedService): int
    {
        $this->info('Fetching news from RSS feeds...');

        $articles = $rssFeedService->fetchAll();
        $this->info("Found " . count($articles) . " articles");

        $stored = $rssFeedService->storeArticles($articles);
        $this->info("Stored " . count($stored) . " new articles");

        if ($this->option('scrape') && !empty($stored)) {
            $this->info('Dispatching scrape jobs for new articles...');
            foreach ($stored as $article) {
                \App\Jobs\ScrapeArticleJob::dispatch($article);
            }
            $this->info("Dispatched " . count($stored) . " scrape jobs");
        }

        return Command::SUCCESS;
    }
}
