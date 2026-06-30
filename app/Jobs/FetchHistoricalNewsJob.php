<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\RssFeedService;

class FetchHistoricalNewsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(
        public int $days = 15
    ) {}

    public function handle(RssFeedService $rssFeedService): void
    {
        $count = $rssFeedService->fetchHistoricalFromGoogle($this->days);

        Cache::put('bing_historical_lock', true, now()->addHours(12));

        Log::info("FetchHistoricalNewsJob completed: {$count} articles fetched.");
    }
}
