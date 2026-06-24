<?php

namespace App\Console\Commands;

use App\Services\RssFeedService;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature = 'nalabumi:fetch-news
        {--scrape : Immediately scrape articles}
        {--analyze : Scrape and analyze articles (implies --scrape)}';
    protected $description = 'Fetch latest news from RSS feeds';

    public function handle(RssFeedService $rssFeedService): int
    {
        $this->info('🌿 Nalabumi — Fetching news from RSS feeds...');

        $articles = $rssFeedService->fetchAll();
        $this->info("📡 Found " . count($articles) . " articles from all sources");

        $stored = $rssFeedService->storeArticles($articles);
        $this->info("💾 Stored " . count($stored) . " new articles");

        $shouldScrape = $this->option('scrape') || $this->option('analyze');

        if ($shouldScrape && !empty($stored)) {
            $this->info('🔍 Dispatching scrape jobs for new articles...');
            $bar = $this->output->createProgressBar(count($stored));
            $bar->start();

            foreach ($stored as $article) {
                \App\Jobs\ScrapeArticleJob::dispatch($article);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Dispatched " . count($stored) . " scrape jobs");

            if ($this->option('analyze')) {
                $this->info('🤖 Articles will be automatically analyzed after scraping (via job chain)');
            }
        }

        // Summary table
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Articles Found', count($articles)],
                ['New Articles Stored', count($stored)],
                ['Jobs Dispatched', $shouldScrape ? count($stored) : 0],
            ]
        );

        return Command::SUCCESS;
    }
}
