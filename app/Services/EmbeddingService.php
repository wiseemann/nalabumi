<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function generateEmbedding(Article $article): ?array
    {
        $text = $this->prepareText($article);

        if (empty($text)) {
            Log::warning("No text to embed for article {$article->id}");
            return null;
        }

        try {
            $embedding = $this->callEmbeddingApi($text);

            $article->update(['has_embedding' => true]);

            return $embedding;

        } catch (\Exception $e) {
            Log::error("Embedding generation failed for article {$article->id}: {$e->getMessage()}");
            return null;
        }
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
            return $loc->full_location ?? $loc->name;
        })->join(', ');
        if ($locations) {
            $parts[] = "Locations: {$locations}";
        }

        return implode("\n", $parts);
    }

    protected function callEmbeddingApi(string $text): array
    {
        $apiUrl = env('EMBEDDING_API_URL');

        if (empty($apiUrl)) {
            throw new \Exception('Embedding API URL not configured');
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($apiUrl, [
                'model' => env('EMBEDDING_MODEL', 'bge-m3'),
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
