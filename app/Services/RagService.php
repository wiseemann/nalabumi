<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected QdrantService $qdrantService
    ) {}

    /**
     * Answer a user's question with geographic context awareness.
     */
    public function answerQuestion(string $question, ?string $locationHint = null): array
    {
        $queryEmbedding = $this->embeddingService->queryEmbedding($question);

        if (!$queryEmbedding) {
            return [
                'answer' => 'Maaf, terjadi kesalahan dalam memproses pertanyaan Anda.',
                'thought' => null,
                'sources' => [],
            ];
        }

        // Detect geographic context from the question or explicit location hint
        $detectedLocation = $this->detectLocation($question, $locationHint);

        // Use hybrid search: filtered (location-specific) + unfiltered (general)
        $searchResults = $this->hybridSearch($queryEmbedding, $detectedLocation);

        if (empty($searchResults)) {
            return [
                'answer' => 'Maaf, saya tidak menemukan informasi yang relevan dengan pertanyaan Anda dalam database.',
                'thought' => null,
                'sources' => [],
            ];
        }

        $context = $this->buildContext($searchResults);
        $generated = $this->generateAnswer($question, $context, $detectedLocation);
        $sources = $this->extractSources($searchResults);

        return [
            'answer' => $generated['answer'],
            'thought' => $generated['thought'],
            'sources' => $sources,
            'detected_location' => $detectedLocation,
        ];
    }

    /**
     * Detect location from the user's question by matching against known regency/province names.
     */
    protected function detectLocation(string $question, ?string $locationHint): ?array
    {
        $searchText = strtolower($question . ' ' . ($locationHint ?? ''));

        // First try to match against known locations in the database
        $locations = Location::select('regency_name', 'province_name')
            ->distinct()
            ->get();

        $bestMatch = null;
        $bestMatchLength = 0;

        foreach ($locations as $location) {
            // Check regency name
            if ($location->regency_name) {
                $regencyLower = strtolower($location->regency_name);
                // Try full name first, then without prefix
                $variants = [$regencyLower];

                // Generate variants without prefix (Kabupaten/Kota)
                $stripped = preg_replace('/^(kabupaten|kota)\s+/i', '', $regencyLower);
                if ($stripped !== $regencyLower) {
                    $variants[] = $stripped;
                }

                foreach ($variants as $variant) {
                    if (str_contains($searchText, $variant) && strlen($variant) > $bestMatchLength) {
                        $bestMatch = [
                            'regency' => $location->regency_name,
                            'province' => $location->province_name,
                        ];
                        $bestMatchLength = strlen($variant);
                    }
                }
            }

            // Check province name
            if ($location->province_name && !$bestMatch) {
                $provinceLower = strtolower($location->province_name);
                if (str_contains($searchText, $provinceLower) && strlen($provinceLower) > $bestMatchLength) {
                    $bestMatch = [
                        'regency' => null,
                        'province' => $location->province_name,
                    ];
                    $bestMatchLength = strlen($provinceLower);
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Hybrid search: prioritize location-specific results, then fill with general results.
     */
    protected function hybridSearch(array $queryEmbedding, ?array $detectedLocation): array
    {
        $limit = 7;

        if ($detectedLocation) {
            // First: search with location filter
            $filter = $this->buildLocationFilter($detectedLocation);
            $locationResults = $this->qdrantService->search($queryEmbedding, $limit, $filter);

            // If we got enough location-specific results, return them
            if (count($locationResults) >= 3) {
                // Fill remaining slots with general results (different articles)
                $existingIds = array_map(fn($r) => $r['id'], $locationResults);
                $generalResults = $this->qdrantService->search($queryEmbedding, $limit - count($locationResults));

                // Merge, avoiding duplicates
                foreach ($generalResults as $result) {
                    if (!in_array($result['id'], $existingIds)) {
                        $locationResults[] = $result;
                        if (count($locationResults) >= $limit) break;
                    }
                }

                return $locationResults;
            }

            // If not enough location-specific results, fall back to general search
            // but prepend whatever location results we found
            $generalResults = $this->qdrantService->search($queryEmbedding, $limit);
            $existingIds = array_map(fn($r) => $r['id'], $locationResults);

            foreach ($generalResults as $result) {
                if (!in_array($result['id'], $existingIds)) {
                    $locationResults[] = $result;
                    if (count($locationResults) >= $limit) break;
                }
            }

            return $locationResults;
        }

        // No location detected: plain vector similarity search
        return $this->qdrantService->search($queryEmbedding, $limit);
    }

    /**
     * Build a Qdrant filter for location-based search.
     */
    protected function buildLocationFilter(array $location): array
    {
        $conditions = [];

        if (!empty($location['regency'])) {
            $conditions[] = [
                'key' => 'locations[].regency',
                'match' => ['value' => $location['regency']],
            ];
        }

        if (!empty($location['province'])) {
            $conditions[] = [
                'key' => 'locations[].province',
                'match' => ['value' => $location['province']],
            ];
        }

        if (empty($conditions)) {
            return [];
        }

        return [
            'should' => $conditions,
        ];
    }

    protected function buildContext(array $searchResults): string
    {
        $contextParts = [];

        foreach ($searchResults as $i => $result) {
            $payload = $result['payload'] ?? [];
            $articleId = $payload['article_id'] ?? null;

            if ($articleId) {
                $article = Article::with(['locations', 'categories'])->find($articleId);
                if ($article) {
                    $content = substr($article->content ?? $article->title ?? '', 0, 2000);
                    $locations = $article->locations->map(fn($l) => $l->full_location)->join(', ');
                    $categories = $article->categories->pluck('name')->join(', ');

                    $meta = "Sumber: {$article->source}";
                    if ($locations) $meta .= " | Wilayah: {$locations}";
                    if ($categories) $meta .= " | Kategori: {$categories}";
                    if ($article->published_at) $meta .= " | Tanggal: {$article->published_at->format('d M Y')}";

                    $contextParts[] = "--- Artikel " . ($i + 1) . " ---\n{$meta}\n{$content}";
                }
            }
        }

        return implode("\n\n", $contextParts);
    }

    protected function generateAnswer(string $question, string $context, ?array $detectedLocation): array
    {
        $apiUrl = config('services.opencode.api_url') ?? env('OPENCODE_API_URL');
        $apiKey = config('services.opencode.api_key') ?? env('OPENCODE_API_KEY');
        $model = config('services.opencode.model') ?? env('OPENCODE_MODEL', 'deepseek-v4-flash');

        $locationContext = '';
        if ($detectedLocation) {
            $parts = array_filter([$detectedLocation['regency'], $detectedLocation['province']]);
            $locationStr = implode(', ', $parts);
            $locationContext = "\nKONTEKS GEOGRAFIS: Pertanyaan ini terkait dengan wilayah {$locationStr}. Prioritaskan informasi dari wilayah tersebut dalam jawaban Anda.";
        }

        $prompt = <<<PROMPT
Anda adalah asisten chatbot Nalabumi AI yang membantu menjawab pertanyaan tentang lingkungan di Indonesia berdasarkan data berita yang tersedia.

JAWAB pertanyaan berikut berdasarkan KONTEKS yang diberikan. Jika informasi tidak ada dalam konteks, katakan bahwa Anda tidak memiliki informasi tersebut.
{$locationContext}

PERTANYAAN: {$question}

KONTEKS:
{$context}

ATURAN JAWABAN:
1. Berikan jawaban yang informatif, akurat, dan mudah dipahami dalam Bahasa Indonesia
2. Sertakan referensi sumber berita jika relevan
3. Jika pertanyaan tentang wilayah tertentu, fokuskan jawaban pada data dari wilayah tersebut
4. Jika ada beberapa kasus di wilayah yang ditanya, berikan ringkasan terstruktur
5. Sebutkan tingkat keparahan (severity) jika informasi tersedia
PROMPT;

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->post($apiUrl, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Anda adalah asisten Nalabumi AI yang ahli dalam isu lingkungan Indonesia. Jawab berdasarkan konteks yang diberikan. Jawab dalam Bahasa Indonesia yang baik dan mudah dipahami.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                ]);

            if (!$response->successful()) {
                throw new \Exception("AI API error: {$response->status()}");
            }

            $data = $response->json();
            $message = $data['choices'][0]['message'] ?? [];
            $content = $message['content'] ?? 'Maaf, saya tidak dapat memproses jawaban.';
            $thoughtProcess = $message['reasoning_content'] ?? null;

            if (!$thoughtProcess && preg_match('/<think>(.*?)<\/think>/is', $content, $matches)) {
                $thoughtProcess = trim($matches[1]);
                $content = trim(str_replace($matches[0], '', $content));
            }

            return [
                'answer' => $content,
                'thought' => $thoughtProcess,
            ];

        } catch (\Exception $e) {
            Log::error("RAG answer generation failed: {$e->getMessage()}");
            return [
                'answer' => 'Maaf, terjadi kesalahan dalam memproses jawaban Anda.',
                'thought' => null,
            ];
        }
    }

    protected function extractSources(array $searchResults): array
    {
        $sources = [];
        $seenIds = [];

        foreach ($searchResults as $result) {
            $payload = $result['payload'] ?? [];
            $articleId = $payload['article_id'] ?? null;

            if ($articleId && !in_array($articleId, $seenIds)) {
                $article = Article::find($articleId);
                if ($article) {
                    $sources[] = [
                        'id' => $article->id,
                        'title' => $article->title,
                        'url' => $article->url,
                        'source' => $article->source,
                        'published_at' => $article->published_at,
                    ];
                    $seenIds[] = $articleId;
                }
            }
        }

        return $sources;
    }
}
