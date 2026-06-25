<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantService
{
    protected string $host;
    protected string $collection;

    public function __construct()
    {
        $this->host = rtrim(config('services.qdrant.host', env('QDRANT_HOST', 'http://127.0.0.1:6333')), '/');
        $this->collection = config('services.qdrant.collection', env('QDRANT_COLLECTION', 'articles'));
    }

    public function ensureCollection(): void
    {
        $collections = $this->listCollections();

        if (!in_array($this->collection, $collections)) {
            $this->createCollection();
        }
    }

    protected function listCollections(): array
    {
        $response = Http::get("{$this->host}/collections");
        $data = $response->json();
        return array_map(fn($c) => $c['name'], $data['result']['collections'] ?? []);
    }

    protected function createCollection(): void
    {
        $response = Http::put("{$this->host}/collections/{$this->collection}", [
            'vectors' => [
                'size' => 1024,
                'distance' => 'Cosine',
            ],
            'optimizers_config' => [
                'memmap_threshold' => 20000,
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to create Qdrant collection: {$response->body()}");
        }

        Log::info("Created Qdrant collection: {$this->collection}");
    }

    public function upsertPoint(int|string $id, array $vector, array $payload): bool
    {
        $pointId = is_numeric($id) ? (int) $id : $id;

        $body = json_encode([
            'points' => [
                [
                    'id' => $pointId,
                    'vector' => $vector,
                    'payload' => $payload,
                ],
            ],
        ]);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->withBody($body)
            ->put("{$this->host}/collections/{$this->collection}/points?wait=true");

        if (!$response->successful()) {
            Log::error("Failed to upsert point to Qdrant: {$response->body()}");
            return false;
        }

        return true;
    }

    public function search(array $vector, int $limit = 10, array $filter = []): array
    {
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        if (!empty($filter)) {
            $body['filter'] = $filter;
        }

        $response = Http::post("{$this->host}/collections/{$this->collection}/points/search", $body);

        if (!$response->successful()) {
            Log::error("Qdrant search failed: {$response->body()}");
            return [];
        }

        $data = $response->json();
        return $data['result'] ?? [];
    }

    public function deletePoint(int|string $id): bool
    {
        $pointId = is_numeric($id) ? (int) $id : $id;

        $response = Http::post("{$this->host}/collections/{$this->collection}/points/delete", [
            'points' => [$pointId],
        ]);

        return $response->successful();
    }

    public function getPointCount(): int
    {
        $response = Http::get("{$this->host}/collections/{$this->collection}");
        $data = $response->json();
        return $data['result']['points_count'] ?? 0;
    }
}
