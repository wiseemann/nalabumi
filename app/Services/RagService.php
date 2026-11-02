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
    public function answerQuestion(string $question, ?string $locationHint = null, array $history = []): array
    {
        // Detect geographic context from the question, explicit location hint, or recent history
        $detectedLocation = $this->detectLocation($question, $locationHint, $history);

        // 1. Try Vector Hybrid Search if embedding is available (Ollama)
        $queryEmbedding = $this->embeddingService->queryEmbedding($question);
        $searchResults = [];

        if ($queryEmbedding) {
            $searchResults = $this->hybridSearch($queryEmbedding, $detectedLocation);
        }

        // 2. Fallback to SQL keyword search if vector search is unavailable or returns empty
        if (empty($searchResults)) {
            $searchResults = $this->searchArticlesByKeyword($question, $detectedLocation);
        }

        $context = $this->buildContext($searchResults);
        $generated = $this->generateAnswer($question, $context, $detectedLocation, $history);
        $sources = $this->extractSources($searchResults);

        return [
            'answer' => $generated['answer'],
            'thought' => $generated['thought'],
            'sources' => $sources,
            'detected_location' => $detectedLocation,
        ];
    }

    /**
     * Stream an answer using Server-Sent Events (yields event chunks).
     *
     * @return \Generator
     */
    public function streamAnswer(string $question, ?string $locationHint = null, array $history = []): \Generator
    {
        // Detect location context
        $detectedLocation = $this->detectLocation($question, $locationHint, $history);

        // 1. Try Vector search if Ollama embedding is available
        $queryEmbedding = $this->embeddingService->queryEmbedding($question);
        $searchResults = [];

        if ($queryEmbedding) {
            $searchResults = $this->hybridSearch($queryEmbedding, $detectedLocation);
        }

        // 2. Fallback to SQL keyword search if vector search is unavailable (e.g. Ollama offline)
        if (empty($searchResults)) {
            $searchResults = $this->searchArticlesByKeyword($question, $detectedLocation);
        }

        $sources = $this->extractSources($searchResults);

        // Yield metadata (detected location + sources) immediately so client UI can display them
        yield [
            'type' => 'meta',
            'detected_location' => $detectedLocation,
            'sources' => $sources,
        ];

        $context = $this->buildContext($searchResults);
        $messages = $this->buildLlmMessages($question, $context, $detectedLocation, $history);

        $apiUrl = config('services.opencode.api_url') ?? env('OPENCODE_API_URL');
        $apiKey = config('services.opencode.api_key') ?? env('OPENCODE_API_KEY');
        $model = config('services.opencode.model') ?? env('OPENCODE_MODEL', 'deepseek-v4-flash');

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                    'x-opencode-session' => (string) \Illuminate\Support\Str::uuid(),
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                    'stream' => true,
                ],
                'stream' => true,
                'timeout' => 120,
            ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = \GuzzleHttp\Psr7\Utils::readLine($body);
                $trimmed = trim($line);

                if ($trimmed === '' || $trimmed === 'data: [DONE]') {
                    if ($trimmed === 'data: [DONE]') {
                        break;
                    }
                    continue;
                }

                if (str_starts_with($trimmed, 'data: ')) {
                    $jsonStr = substr($trimmed, 6);
                    $chunk = json_decode($jsonStr, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($chunk['choices'][0]['delta'])) {
                        $delta = $chunk['choices'][0]['delta'];

                        $thoughtChunk = $delta['reasoning_content'] ?? $delta['reasoning'] ?? null;
                        if (!empty($thoughtChunk)) {
                            yield [
                                'type' => 'thought',
                                'delta' => $thoughtChunk,
                            ];
                        }

                        if (isset($delta['content']) && $delta['content'] !== '') {
                            yield [
                                'type' => 'token',
                                'delta' => $delta['content'],
                            ];
                        }
                    }
                }
            }

            yield ['type' => 'done'];

        } catch (\Exception $e) {
            Log::error("RAG streaming answer generation failed: {$e->getMessage()}");
            yield [
                'type' => 'error',
                'message' => 'Maaf, terjadi kesalahan saat menghubungi layanan AI.',
            ];
            yield ['type' => 'done'];
        }
    }

    /**
     * Detect location from the user's question, location hint, or previous turns in conversation history.
     */
    protected function detectLocation(string $question, ?string $locationHint = null, array $history = []): ?array
    {
        $searchText = strtolower($question . ' ' . ($locationHint ?? ''));

        // Query distinct locations once
        $locations = Location::select('regency_name', 'province_name')
            ->distinct()
            ->get();

        // 1. Try matching the current question & explicit hint
        $matched = $this->matchLocationInText($searchText, $locations);
        if ($matched) {
            return $matched;
        }

        // 2. If no location found in current question, inspect recent conversation history in reverse order
        if (!empty($history)) {
            $reversedHistory = array_reverse($history);
            foreach ($reversedHistory as $turn) {
                $turnContent = strtolower($turn['content'] ?? '');
                if (!empty($turnContent)) {
                    $historyMatch = $this->matchLocationInText($turnContent, $locations);
                    if ($historyMatch) {
                        return $historyMatch;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Helper to match regency or province from a given text string using word boundaries and phrase safety.
     */
    protected function matchLocationInText(string $text, $locations): ?array
    {
        $bestMatch = null;
        $bestMatchLength = 0;

        // Contextual exclusions for homonyms / common phrases
        // e.g. "batu bara" shouldn't trigger "Kota Batu" unless explicitly prefixed with "kota/kabupaten"
        $ambiguousExclusions = [
            'batu' => ['batu bara', 'batubara'],
            'pekan' => ['pekan ini', 'pekan lalu', 'pekan depan', 'setiap pekan'],
        ];

        foreach ($locations as $location) {
            // Check regency name
            if ($location->regency_name) {
                $regencyLower = strtolower($location->regency_name);
                $variants = [$regencyLower];

                // Generate variants without prefix (Kabupaten/Kota)
                $stripped = trim(preg_replace('/^(kabupaten|kota administrasi|kota)\s+/iu', '', $regencyLower));
                if ($stripped !== $regencyLower && mb_strlen($stripped) >= 3) {
                    $variants[] = $stripped;
                }

                foreach ($variants as $variant) {
                    // Check ambiguous exclusions
                    if (isset($ambiguousExclusions[$variant])) {
                        $isExcluded = false;
                        foreach ($ambiguousExclusions[$variant] as $exclusion) {
                            if (str_contains($text, $exclusion) && !str_contains($text, 'kota ' . $variant) && !str_contains($text, 'kabupaten ' . $variant)) {
                                $isExcluded = true;
                                break;
                            }
                        }
                        if ($isExcluded) {
                            continue;
                        }
                    }

                    // Strict word boundary matching
                    $pattern = '/\b' . preg_quote($variant, '/') . '\b/iu';
                    if (preg_match($pattern, $text) && mb_strlen($variant) > $bestMatchLength) {
                        $bestMatch = [
                            'regency' => $location->regency_name,
                            'province' => $location->province_name,
                        ];
                        $bestMatchLength = mb_strlen($variant);
                    }
                }
            }

            // Check province name if no regency match yet
            if ($location->province_name && !$bestMatch) {
                $provinceLower = strtolower($location->province_name);
                $pattern = '/\b' . preg_quote($provinceLower, '/') . '\b/iu';
                if (preg_match($pattern, $text) && mb_strlen($provinceLower) > $bestMatchLength) {
                    $bestMatch = [
                        'regency' => null,
                        'province' => $location->province_name,
                    ];
                    $bestMatchLength = mb_strlen($provinceLower);
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
            $locationResults = $this->deduplicateByArticle($this->qdrantService->search($queryEmbedding, $limit * 2, $filter));

            // If we got enough location-specific results, return them
            if (count($locationResults) >= 3) {
                $existingArticleIds = array_map(fn($r) => $r['payload']['article_id'] ?? $r['id'], $locationResults);
                $generalResults = $this->deduplicateByArticle($this->qdrantService->search($queryEmbedding, $limit * 2));

                // Merge, avoiding duplicate articles
                foreach ($generalResults as $result) {
                    $artId = $result['payload']['article_id'] ?? $result['id'];
                    if (!in_array($artId, $existingArticleIds)) {
                        $locationResults[] = $result;
                        if (count($locationResults) >= $limit) break;
                    }
                }

                return array_slice($locationResults, 0, $limit);
            }

            // Fallback: merge location results with general results
            $generalResults = $this->deduplicateByArticle($this->qdrantService->search($queryEmbedding, $limit * 2));
            $existingArticleIds = array_map(fn($r) => $r['payload']['article_id'] ?? $r['id'], $locationResults);

            foreach ($generalResults as $result) {
                $artId = $result['payload']['article_id'] ?? $result['id'];
                if (!in_array($artId, $existingArticleIds)) {
                    $locationResults[] = $result;
                    if (count($locationResults) >= $limit) break;
                }
            }

            return array_slice($locationResults, 0, $limit);
        }

        // No location detected: plain vector similarity search deduplicated by article
        return array_slice($this->deduplicateByArticle($this->qdrantService->search($queryEmbedding, $limit * 2)), 0, $limit);
    }

    /**
     * Deduplicate search results by article_id to avoid redundant chunks from the same article.
     */
    protected function deduplicateByArticle(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $res) {
            $artId = $res['payload']['article_id'] ?? $res['id'];
            if (!in_array($artId, $seen)) {
                $seen[] = $artId;
                $unique[] = $res;
            }
        }

        return $unique;
    }

    /**
     * Build Qdrant filter for location-based search.
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
                    // Prefer semantically matched chunk text if available, fallback to full content slice
                    $matchedChunk = $payload['chunk_text'] ?? null;
                    $content = $matchedChunk ?: substr($article->content ?? $article->title ?? '', 0, 2000);

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

    /**
     * Build the structured message history and prompt for the LLM.
     */
    protected function buildLlmMessages(string $question, string $context, ?array $detectedLocation, array $history = []): array
    {
        $locationContext = '';
        if ($detectedLocation) {
            $parts = array_filter([$detectedLocation['regency'], $detectedLocation['province']]);
            $locationStr = implode(', ', $parts);
            $locationContext = "\nKONTEKS GEOGRAFIS: Pertanyaan ini terkait dengan wilayah {$locationStr}. Prioritaskan informasi dari wilayah tersebut dalam jawaban Anda.";
        }

        $systemPrompt = 'Anda adalah asisten Nalabumi AI yang ahli dalam isu dan kelestarian lingkungan di Indonesia. Jawab berdasarkan konteks berita yang diberikan secara akurat, lugas, dan terstruktur dalam Bahasa Indonesia.';

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ]
        ];

        // Include recent conversation turns (sanitized, max last 6 turns)
        if (!empty($history)) {
            $recentHistory = array_slice($history, -6);
            foreach ($recentHistory as $turn) {
                $role = in_array($turn['role'] ?? '', ['user', 'assistant']) ? $turn['role'] : null;
                $content = trim($turn['content'] ?? '');
                if ($role && $content) {
                    $messages[] = [
                        'role' => $role,
                        'content' => mb_substr($content, 0, 1000),
                    ];
                }
            }
        }

        $userPrompt = <<<PROMPT
JAWAB pertanyaan berikut berdasarkan KONTEKS BERITA yang diberikan. Jika informasi tidak ada dalam konteks, katakan dengan jujur bahwa Anda tidak memiliki informasi tersebut dalam database saat ini.
{$locationContext}

PERTANYAAN: {$question}

KONTEKS BERITA:
{$context}

ATURAN JAWABAN:
1. Berikan jawaban yang informatif, akurat, dan mudah dipahami dalam Bahasa Indonesia.
2. Sertakan referensi sumber berita atau data wilayah jika relevan.
3. Jika pertanyaan tentang wilayah tertentu, fokuskan jawaban pada data dari wilayah tersebut.
4. Jika ada beberapa kasus di wilayah yang ditanya, berikan ringkasan terstruktur berbentuk poin-poin.
5. Sebutkan tingkat keparahan (severity) atau dampak kerusakan jika informasi tersedia.
PROMPT;

        $messages[] = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        return $messages;
    }

    protected function generateAnswer(string $question, string $context, ?array $detectedLocation, array $history = []): array
    {
        $apiUrl = config('services.opencode.api_url') ?? env('OPENCODE_API_URL');
        $apiKey = config('services.opencode.api_key') ?? env('OPENCODE_API_KEY');
        $model = config('services.opencode.model') ?? env('OPENCODE_MODEL', 'deepseek-v4-flash');

        $messages = $this->buildLlmMessages($question, $context, $detectedLocation, $history);

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                    'x-opencode-session' => (string) \Illuminate\Support\Str::uuid(),
                ])
                ->post($apiUrl, [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                ]);

            if (!$response->successful()) {
                throw new \Exception("AI API error: {$response->status()}");
            }

            $data = $response->json();
            $message = $data['choices'][0]['message'] ?? [];
            $content = $message['content'] ?? 'Maaf, saya tidak dapat memproses jawaban.';
            $thoughtProcess = $message['reasoning_content'] ?? $message['reasoning'] ?? null;

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

    /**
     * Resilient SQL keyword search fallback when Ollama / vector embeddings are unavailable.
     */
    protected function searchArticlesByKeyword(string $question, ?array $detectedLocation): array
    {
        $stopWords = ['apa', 'apakah', 'bagaimana', 'dimana', 'kapan', 'siapa', 'mengapa', 'kenapa', 'di', 'ke', 'dari', 'yang', 'pada', 'adalah', 'ini', 'itu', 'dan', 'atau', 'untuk', 'dengan', 'tentang', 'terkait', 'tolong', 'jelaskan', 'ceritakan', 'berikan', 'kasus', 'berita', 'ada'];
        $words = preg_split('/\s+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array($w, $stopWords));

        $query = Article::where('is_analyzed', true)
            ->with(['locations', 'categories']);

        if ($detectedLocation) {
            $query->whereHas('locations', function ($q) use ($detectedLocation) {
                if (!empty($detectedLocation['regency'])) {
                    $q->where('regency_name', 'like', "%{$detectedLocation['regency']}%");
                } elseif (!empty($detectedLocation['province'])) {
                    $q->where('province_name', 'like', "%{$detectedLocation['province']}%");
                }
            });
        }

        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('title', 'like', "%{$kw}%")
                      ->orWhere('content', 'like', "%{$kw}%");
                }
            });
        }

        $articles = $query->orderByDesc('published_at')->limit(5)->get();

        // If location filter was too strict, fallback to matching keywords across all analyzed articles
        if ($articles->isEmpty() && !empty($keywords)) {
            $articles = Article::where('is_analyzed', true)
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('title', 'like', "%{$kw}%")
                          ->orWhere('content', 'like', "%{$kw}%");
                    }
                })
                ->with(['locations', 'categories'])
                ->orderByDesc('published_at')
                ->limit(5)
                ->get();
        }

        // If still empty (e.g. broad greeting or generic query), take the latest 3 prominent cases
        if ($articles->isEmpty()) {
            $articles = Article::where('is_analyzed', true)
                ->has('cases')
                ->with(['locations', 'categories'])
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();
        }

        $results = [];
        foreach ($articles as $art) {
            $results[] = [
                'id' => $art->id,
                'score' => 1.0,
                'payload' => [
                    'article_id' => $art->id,
                    'title' => $art->title,
                    'source' => $art->source,
                    'url' => $art->url,
                    'chunk_text' => mb_substr($art->content ?? $art->title ?? '', 0, 2000),
                ],
            ];
        }

        return $results;
    }
}
