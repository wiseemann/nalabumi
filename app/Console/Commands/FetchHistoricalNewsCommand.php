<?php

namespace App\Console\Commands;

use App\Services\RssFeedService;
use Illuminate\Console\Command;

class FetchHistoricalNewsCommand extends Command
{
    protected $signature = 'nalabumi:fetch-historical {--days=15 : Number of days to look back}';
    protected $description = 'Fetch historical news from Bing News RSS using 25 specific keywords';

    public function handle(RssFeedService $rssFeedService): int
    {
        $this->info('🌿 Nalabumi — Fetching historical news from Bing (25 Keywords)...');
        
        $days = (int) $this->option('days');
        $storedCount = $rssFeedService->fetchHistoricalFromGoogle($days);
        
        $this->info("✅ Successfully fetched and dispatched {$storedCount} historical articles.");

        return Command::SUCCESS;
    }
}
