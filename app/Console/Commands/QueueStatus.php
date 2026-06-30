<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('queue:status')]
#[Description('Check queue status: pending jobs, failed jobs, and counts by type')]
class QueueStatus extends Command
{
    public function handle()
    {
        $this->info('Nalabumi Queue Status');
        $this->newLine();

        // --- Pending Jobs from Redis ---
        $queueKey = 'queues:default';
        $rawJobs = \Illuminate\Support\Facades\Redis::lrange($queueKey, 0, -1) ?: [];
        $allJobs = collect($rawJobs)
            ->map(fn($raw) => json_decode($raw, true))
            ->filter();

        $pending = $allJobs->count();

        $this->line("  <fg=cyan>Pending Jobs:</> <fg=yellow>{$pending}</>");

        if ($pending > 0) {
            $grouped = $allJobs->groupBy(fn($j) => $j['displayName'] ?? 'Unknown')
                ->map(fn($g) => count($g))
                ->sortDesc();

            $this->line("  Breakdown by class:");
            foreach ($grouped as $class => $count) {
                $short = class_basename($class);
                $pct = round($count / $pending * 100, 1);
                $bar = str_repeat('█', max(1, (int)($count / $pending * 30)));
                $this->line("    <fg=green>{$short}</>  {$bar}  {$count} ({$pct}%)");
            }
        }

        // --- Reserved / In-Progress ---
        $reservedKey = 'queues:default:reserved';
        $processing = 0;
        $reservedJobs = [];
        if (\Illuminate\Support\Facades\Redis::exists($reservedKey)) {
            $type = \Illuminate\Support\Facades\Redis::type($reservedKey);
            if ($type === 'list') {
                $reservedJobs = \Illuminate\Support\Facades\Redis::lrange($reservedKey, 0, -1) ?: [];
                $processing = count($reservedJobs);
            }
        }

        $this->newLine();
        $this->line("  <fg=cyan>Processing Jobs:</> <fg=yellow>{$processing}</>");

        if ($processing > 0) {
            foreach ($reservedJobs as $raw) {
                $payload = json_decode($raw, true);
                $name = class_basename($payload['displayName'] ?? 'Unknown');
                $attempts = $payload['attempts'] ?? 0;
                $id = $payload['id'] ?? '—';
                $this->line("    <fg=blue>[{$id}]</> {$name} (attempt: {$attempts})");
            }
        }

        // --- Delayed Jobs ---
        $this->newLine();
        $delayedKey = 'queues:default:delayed';
        $delayedCount = 0;
        if (\Illuminate\Support\Facades\Redis::exists($delayedKey)) {
            $type = \Illuminate\Support\Facades\Redis::type($delayedKey);
            if ($type === 'zset') {
                $delayedCount = \Illuminate\Support\Facades\Redis::zcard($delayedKey);
            }
        }
        $this->line("  <fg=cyan>Delayed Jobs:</> <fg=yellow>{$delayedCount}</>");

        // --- Failed Jobs ---
        $this->newLine();
        $failedTotal = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
        $this->line("  <fg=cyan>Failed Jobs:</> <fg=red>{$failedTotal}</>");

        if ($failedTotal > 0) {
            $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(10)
                ->get();

            $this->line("  Latest 10 failures:");
            foreach ($failed as $job) {
                $payload = json_decode($job->payload, true);
                $name = class_basename($payload['displayName'] ?? 'Unknown');
                $preview = mb_substr($job->exception, 0, 80);
                $this->line("    <fg=red>[{$job->failed_at}]</> {$name}: {$preview}");
            }
        }

        // --- Scheduler status ---
        $this->newLine();
        $this->line("  <fg=cyan>Worker Process:</>");
        $output = @shell_exec('ps aux | grep "queue:\(listen\|work\)" | grep -v grep 2>/dev/null');
        if ($output && trim($output)) {
            $lines = array_filter(explode("\n", trim($output)));
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', $line, 11);
                $cmd = end($parts);
                $this->line("    <fg=green>PID {$parts[1]}</> {$cmd}");
            }
        } else {
            $this->line("    <fg=red>No queue worker running</>");
        }

        $this->newLine();
    }
}
