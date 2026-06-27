<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\EmbeddingService;
use App\Services\QdrantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $timeout = 120;

    public function __construct(
        public Article $article
    ) {}

    public function handle(EmbeddingService $embeddingService, QdrantService $qdrantService): void
    {
        $qdrantService->ensureCollection();

        $embedding = $embeddingService->generateEmbedding($this->article);

        if (!$embedding) {
            return;
        }

        $payload = [
            'article_id' => $this->article->id,
            'title' => $this->article->title,
            'source' => $this->article->source,
            'url' => $this->article->url,
            'published_at' => $this->article->published_at?->toDateString(),
            'categories' => $this->article->categories->pluck('name')->toArray(),
            'locations' => $this->article->locations->map(function ($loc) {
                return [
                    'name' => $loc->name,
                    'regency' => $loc->regency_name,
                    'province' => $loc->province_name,
                ];
            })->toArray(),
        ];

        $qdrantService->upsertPoint(
            (int) $this->article->id,
            $embedding,
            $payload
        );

        CalculateReliabilityJob::dispatch($this->article);
    }
}
