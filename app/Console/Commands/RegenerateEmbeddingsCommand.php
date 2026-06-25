<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Jobs\GenerateEmbeddingJob;
use Illuminate\Console\Command;

class RegenerateEmbeddingsCommand extends Command
{
    protected $signature = 'nalabumi:regenerate-embeddings';
    protected $description = 'Regenerate Qdrant embeddings for all analyzed articles';

    public function handle(): int
    {
        $articles = Article::where('is_analyzed', true)->get();
        $count = $articles->count();

        $this->info("Regenerating embeddings for {$count} articles...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($articles as $article) {
            $article->update(['has_embedding' => false]);
            GenerateEmbeddingJob::dispatch($article);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Dispatched {$count} embedding jobs. Run php artisan queue:listen --tries=1 to process.");

        return Command::SUCCESS;
    }
}
