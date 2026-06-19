<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected QdrantService $qdrantService
    ) {}

    public function answerQuestion(string $question): array
    {
        $queryEmbedding = $this->embeddingService->queryEmbedding($question);

        if (!$queryEmbedding) {
            return [
                'answer' => 'Maaf, terjadi kesalahan dalam memproses pertanyaan Anda.',
                'sources' => [],
            ];
        }

        $searchResults = $this->qdrantService->search($queryEmbedding, 5);

        if (empty($searchResults)) {
            return [
                'answer' => 'Maaf, saya tidak menemukan informasi yang relevan dengan pertanyaan Anda dalam database.',
                'sources' => [],
            ];
        }

        $context = $this->buildContext($searchResults);
        $answer = $this->generateAnswer($question, $context);
        $sources = $this->extractSources($searchResults);

        return [
            'answer' => $answer,
            'sources' => $sources,
        ];
    }

    protected function buildContext(array $searchResults): string
    {
        $contextParts = [];

        foreach ($searchResults as $i => $result) {
            $payload = $result['payload'] ?? [];
            $articleId = $payload['article_id'] ?? null;

            if ($articleId) {
                $article = Article::find($articleId);
                if ($article) {
                    $content = substr($article->content ?? $article->title ?? '', 0, 2000);
                    $contextParts[] = "Artikel {$i} ({$article->source}): {$content}";
                }
            }
        }

        return implode("\n\n---\n\n", $contextParts);
    }

    protected function generateAnswer(string $question, string $context): string
    {
        $apiUrl = env('OPENCODE_API_URL');
        $apiKey = env('OPENCODE_API_KEY');

        $prompt = <<<PROMPT
Anda adalah asisten chatbot Nalabumi AI yang membantu menjawab pertanyaan tentang lingkungan di Indonesia berdasarkan data berita yang tersedia.

JAWAB pertanyaan berikut berdasarkan KONTEKS yang diberikan. Jika informasi tidak ada dalam konteks, katakan bahwa Anda tidak memiliki informasi tersebut.

PERTANYAAN: {$question}

KONTEKS:
{$context}

Berikan jawaban yang informatif, akurat, dan mudah dipahami. Sertakan referensi sumber berita jika relevan.
PROMPT;

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->post($apiUrl, [
                    'model' => env('OPENCODE_MODEL', 'qwen3.6-plus'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Anda adalah asisten Nalabumi AI yang ahli dalam isu lingkungan Indonesia. Jawab berdasarkan konteks yang diberikan.',
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
            return $data['choices'][0]['message']['content'] ?? 'Maaf, saya tidak dapat memproses jawaban.';

        } catch (\Exception $e) {
            Log::error("RAG answer generation failed: {$e->getMessage()}");
            return 'Maaf, terjadi kesalahan dalam memproses jawaban Anda.';
        }
    }

    protected function extractSources(array $searchResults): array
    {
        $sources = [];

        foreach ($searchResults as $result) {
            $payload = $result['payload'] ?? [];
            $articleId = $payload['article_id'] ?? null;

            if ($articleId) {
                $article = Article::find($articleId);
                if ($article) {
                    $sources[] = [
                        'id' => $article->id,
                        'title' => $article->title,
                        'url' => $article->url,
                        'source' => $article->source,
                        'published_at' => $article->published_at,
                    ];
                }
            }
        }

        return $sources;
    }
}
