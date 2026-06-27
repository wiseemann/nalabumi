<?php

namespace App\Services;

use App\Models\Article;
use App\Models\MediaReputation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArticleReliabilityService
{
    public function __construct(
        protected QdrantService $qdrantService,
        protected EmbeddingService $embeddingService
    ) {}

    /**
     * Calculate and save reliability score for an article.
     */
    public function analyzeReliability(Article $article): ?array
    {
        if (empty($article->content) && empty($article->title)) {
            return null;
        }

        try {
            // 1. Cross-Reporting (Coverage) Check
            $similarArticles = $this->findSimilarArticles($article);
            $coverageScore = $this->calculateCoverageScore(count($similarArticles));

            // 2. Media Reputation Check
            $reputationScore = $this->calculateReputationScore($article->source);

            // 3 & 4. Content Consistency and Recency Check via AI
            $aiCheckResult = $this->analyzeContentWithAi($article, $similarArticles);
            
            $consistencyScore = $aiCheckResult['consistency_score'] ?? 50;
            $recencyScore = $aiCheckResult['recency_score'] ?? 50;

            // Calculate final score
            // Coverage: 30%, Reputation: 25%, Consistency: 25%, Recency: 20%
            $finalScore = ($coverageScore * 0.30) +
                          ($reputationScore * 0.25) +
                          ($consistencyScore * 0.25) +
                          ($recencyScore * 0.20);
            
            $finalScore = min(100, max(0, (int) round($finalScore)));
            
            // Determine level
            $level = $this->determineLevel($finalScore);

            // Save to database
            $article->update([
                'reliability_score' => $finalScore,
                'reliability_level' => $level,
            ]);

            Log::info("Calculated reliability for article {$article->id}: Score {$finalScore}, Level {$level}");

            return [
                'score' => $finalScore,
                'level' => $level,
                'breakdown' => [
                    'coverage' => $coverageScore,
                    'reputation' => $reputationScore,
                    'consistency' => $consistencyScore,
                    'recency' => $recencyScore,
                ]
            ];
        } catch (\Exception $e) {
            Log::error("Failed to calculate reliability for article {$article->id}: {$e->getMessage()}");
            return null;
        }
    }

    protected function findSimilarArticles(Article $article): array
    {
        if (!$article->has_embedding) {
            return [];
        }

        // Get embedding for the current article
        $embedding = $this->embeddingService->queryEmbedding($article->title . ' ' . substr($article->content ?? '', 0, 1000));
        
        if (!$embedding) {
            return [];
        }

        // Search for similar articles, limit 5
        $results = $this->qdrantService->search($embedding, 5);
        $similarArticles = [];
        
        foreach ($results as $result) {
            $payload = $result['payload'] ?? [];
            $articleId = $payload['article_id'] ?? null;
            
            // Filter out the article itself and checking if it's within 7 days
            if ($articleId && $articleId != $article->id) {
                $similarArticle = Article::find($articleId);
                if ($similarArticle && $similarArticle->published_at) {
                    if ($article->published_at) {
                        $diffDays = $article->published_at->diffInDays($similarArticle->published_at);
                        if ($diffDays <= 7) {
                            $similarArticles[] = $similarArticle;
                        }
                    } else {
                        $similarArticles[] = $similarArticle;
                    }
                }
            }
        }
        
        return $similarArticles;
    }

    protected function calculateCoverageScore(int $similarCount): int
    {
        if ($similarCount >= 3) return 100;
        if ($similarCount === 2) return 75;
        if ($similarCount === 1) return 50;
        return 20; // 0 similar articles means it's uncorroborated
    }

    protected function calculateReputationScore(?string $source): int
    {
        if (empty($source)) {
            return 50;
        }

        // Clean source domain (e.g. "https://www.kompas.com" to "kompas.com")
        $domain = strtolower(parse_url($source, PHP_URL_HOST) ?? $source);
        $domain = str_replace('www.', '', $domain);

        $reputation = MediaReputation::where('name', 'LIKE', '%' . $domain . '%')->first();
        
        if ($reputation) {
            return $reputation->score;
        }

        return 50; // Neutral default if unknown
    }

    protected function analyzeContentWithAi(Article $article, array $similarArticles): array
    {
        $content = substr($article->content ?? '', 0, 4000);
        $title = $article->title;

        $similarContext = '';
        foreach ($similarArticles as $i => $sim) {
            $simContent = substr($sim->content ?? '', 0, 1000);
            $similarContext .= "Artikel Pembanding " . ($i+1) . " (Sumber: {$sim->source}):\nJudul: {$sim->title}\nIsi: {$simContent}\n\n";
        }

        if (empty($similarContext)) {
            $similarContext = "TIDAK ADA ARTIKEL PEMBANDING.";
        }

        $prompt = <<<PROMPT
Anda adalah asisten AI pemeriksa fakta (fact-checker). Tugas Anda adalah mengevaluasi keandalan sebuah Artikel Utama berdasarkan dua faktor:
1. KONSISTENSI: Apakah isi Artikel Utama didukung atau bertentangan dengan Artikel Pembanding?
2. KETERKINIAN/ORIGINALITAS: Apakah Artikel Utama membahas kejadian baru (terkini) atau sekadar menceritakan ulang kejadian lama (repost/sejarah) seolah-olah baru terjadi?

ARTIKEL UTAMA:
Judul: {$title}
Isi: {$content}

ARTIKEL PEMBANDING:
{$similarContext}

INSTRUKSI PENILAIAN:
- consistency_score (0-100): Jika Artikel Utama didukung oleh Artikel Pembanding, berikan 100. Jika bertentangan, berikan 0. Jika tidak ada artikel pembanding, berikan 50 (netral).
- recency_score (0-100): Jika Artikel Utama melaporkan kejadian aktual yang original, berikan 100. Jika ini adalah daur ulang kejadian tahun lalu atau informasi basi, berikan 20. Jika ambigu, berikan 50.

Berikan jawaban HANYA dalam format JSON berikut tanpa tambahan teks apapun:
{
    "consistency_score": 0,
    "recency_score": 0
}
PROMPT;

        try {
            $apiUrl = config('services.opencode.api_url') ?? env('OPENCODE_API_URL');
            $apiKey = config('services.opencode.api_key') ?? env('OPENCODE_API_KEY');
            
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ])
                ->post($apiUrl, [
                    'model' => env('OPENCODE_MODEL', 'deepseek-v4-flash'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Jawab HANYA dengan format JSON valid.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                
                // Clean markdown JSON ticks
                $content = preg_replace('/^```json\s*/', '', trim($content));
                $content = preg_replace('/\s*```$/', '', $content);
                
                $parsed = json_decode($content, true);
                
                if ($parsed && isset($parsed['consistency_score'])) {
                    return [
                        'consistency_score' => min(100, max(0, (int)$parsed['consistency_score'])),
                        'recency_score' => min(100, max(0, (int)$parsed['recency_score'])),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("AI Reliability check failed: {$e->getMessage()}");
        }

        return [
            'consistency_score' => empty($similarArticles) ? 50 : 70, // Fallback
            'recency_score' => 70, // Fallback
        ];
    }

    protected function determineLevel(int $score): string
    {
        if ($score >= 90) return 'Sangat Terverifikasi';
        if ($score >= 70) return 'Keyakinan Tinggi';
        if ($score >= 40) return 'Keyakinan Sedang';
        return 'Keyakinan Rendah';
    }
}
