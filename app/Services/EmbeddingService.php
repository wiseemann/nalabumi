<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    /**
     * Chunk text into overlapping windows (in words) to preserve semantic coherence.
     *
     * @param string $text
     * @param int $chunkWords Target word count per chunk (default ~250 words)
     * @param int $overlapWords Overlapping word count between consecutive chunks (default ~50 words)
     * @return array
     */
    public function chunkText(string $text, int $chunkWords = 250, int $overlapWords = 50): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);

        if ($totalWords <= $chunkWords) {
            return [trim($text)];
        }

        $chunks = [];
        $i = 0;
        $step = max(50, $chunkWords - $overlapWords);

        while ($i < $totalWords) {
            $slice = array_slice($words, $i, $chunkWords);
            if (!empty($slice)) {
                $chunks[] = implode(' ', $slice);
            }
            $i += $step;
        }

        return $chunks;
    }

    /**
     * Generate embeddings for article chunks with contextual metadata prefix.
     *
     * @param Article $article
     * @return array Array of ['chunk_index' => int, 'text' => string, 'vector' => array]
     */
    public function generateChunkedEmbeddings(Article $article): array
    {
        $content = trim($article->content ?? $article->title ?? '');
        if (empty($content)) {
            Log::warning("No text to embed for article {$article->id}");
            return [];
        }

        // Context header for contextual chunk retrieval
        $categories = $article->categories->pluck('name')->join(', ');
        $locations = $article->locations->map(fn($l) => $l->full_location)->join('; ');

        $contextHeader = "Judul: {$article->title}";
        if ($categories) $contextHeader .= " | Kategori: {$categories}";
        if ($locations) $contextHeader .= " | Wilayah: {$locations}";

        $rawChunks = $this->chunkText($content, 250, 50);
        $results = [];

        foreach ($rawChunks as $index => $rawChunk) {
            // Contextual prefix ensures individual chunks carry the article title and locations
            $textToEmbed = "{$contextHeader}\n\n{$rawChunk}";

            try {
                $vector = $this->callEmbeddingApi($textToEmbed);
                if (!empty($vector)) {
                    $results[] = [
                        'chunk_index' => $index,
                        'text' => $rawChunk,
                        'vector' => $vector,
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Embedding failed for article {$article->id} chunk {$index}: {$e->getMessage()}");
            }
        }

        if (!empty($results)) {
            $article->update(['has_embedding' => true]);
        }

        return $results;
    }

    public function generateEmbedding(Article $article): ?array
    {
        $chunked = $this->generateChunkedEmbeddings($article);
        return $chunked[0]['vector'] ?? null;
    }

    protected function prepareText(Article $article): string
    {
        $parts = [];

        if ($article->title) {
            $parts[] = "Title: {$article->title}";
        }

        if ($article->content) {
            $content = substr($article->content, 0, 10000);
            $parts[] = "Content: {$content}";
        }

        $categories = $article->categories->pluck('name')->join(', ');
        if ($categories) {
            $parts[] = "Categories: {$categories}";
        }

        $locations = $article->locations->map(function ($loc) {
            $locationParts = array_filter([
                $loc->name,
                $loc->regency_name,
                $loc->province_name,
            ]);
            return implode(', ', $locationParts);
        })->join('; ');

        if ($locations) {
            // Repeat location info to strengthen its signal in the embedding
            $parts[] = "Locations: {$locations}";
            $parts[] = "Wilayah terkait: {$locations}";
        }

        return implode("\n", $parts);
    }

    protected function callEmbeddingApi(string $text): array
    {
        $apiUrl = config('services.embedding.api_url') ?? env('EMBEDDING_API_URL');

        if (empty($apiUrl)) {
            throw new \Exception('Embedding API URL not configured');
        }

        $model = config('services.embedding.model') ?? env('EMBEDDING_MODEL', 'bge-m3');

        $response = Http::timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($apiUrl, [
                'model' => $model,
                'input' => $text,
            ]);

        if (!$response->successful()) {
            throw new \Exception("Embedding API error: {$response->status()} - {$response->body()}");
        }

        $data = $response->json();
        return $data['embeddings'][0] ?? $data['embedding'] ?? [];
    }

    public function queryEmbedding(string $query): ?array
    {
        try {
            return $this->callEmbeddingApi($query);
        } catch (\Exception $e) {
            Log::error("Query embedding failed: {$e->getMessage()}");
            return null;
        }
    }
}
