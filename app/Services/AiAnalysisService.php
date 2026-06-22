<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\EnvironmentalCase;
use App\Models\Location;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalysisService
{
    protected array $categoryList = [
        'Deforestasi',
        'Tambang Ilegal',
        'Kebakaran Hutan',
        'Pencemaran Sungai',
        'Pencemaran Laut',
        'Perburuan Satwa',
        'Kerusakan Pesisir',
        'Limbah Industri',
    ];

    public function analyzeArticle(Article $article): ?array
    {
        if (empty($article->content) && empty($article->title)) {
            Log::warning("Article {$article->id} has no content to analyze");
            return null;
        }

        $prompt = $this->buildAnalysisPrompt($article);

        try {
            $response = $this->callAi($prompt);
            $result = $this->parseAiResponse($response);

            if (!$result) {
                Log::warning("Failed to parse AI response for article {$article->id}");
                return null;
            }

            $this->storeAnalysis($article, $result);

            $article->update([
                'is_analyzed' => true,
                'analyzed_at' => now(),
            ]);

            Log::info("Successfully analyzed article {$article->id}");
            return $result;

        } catch (\Exception $e) {
            Log::error("AI analysis failed for article {$article->id}: {$e->getMessage()}");
            return null;
        }
    }

    protected function buildAnalysisPrompt(Article $article): string
    {
        $content = substr($article->content ?? '', 0, 8000);
        $title = $article->title;

        $categories = implode(', ', $this->categoryList);

        return <<<PROMPT
Anda adalah analis lingkungan yang ahli dalam mengidentifikasi kasus perusakan lingkungan dari berita.

Analisis artikel berita berikut dan ekstrak informasi penting:

JUDUL: {$title}

KONTEN:
{$content}

Berikan analisis dalam format JSON dengan struktur berikut:
{
    "categories": ["kategori1", "kategori2"],
    "locations": [
        {
            "name": "nama lokasi spesifik",
            "regency": "nama kabupaten/kota (jika ada)",
            "province": "nama provinsi (jika ada)"
        }
    ],
    "cause": "penyebab utama kerusakan lingkungan",
    "impact": "dampak yang ditimbulkan",
    "severity": 0-100
}

Kategori yang tersedia: {$categories}

PENTING:
- Pilih kategori yang sesuai dari daftar di atas (bisa lebih dari 1)
- Identifikasi lokasi spesifik yang disebutkan (kabupaten/kota, provinsi)
- Berikan skor severity 0-100 berdasarkan tingkat keparahan
- Jika tidak ada informasi tentang lingkungan, set severity = 0
- Jawab HANYA dalam format JSON, tanpa teks tambahan
PROMPT;
    }

    protected function callAi(string $prompt): string
    {
        $apiUrl = config('services.opencode.api_url') ?? env('OPENCODE_API_URL');
        $apiKey = config('services.opencode.api_key') ?? env('OPENCODE_API_KEY');

        if (empty($apiUrl)) {
            throw new \Exception('OpenCode API URL not configured');
        }

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
                        'content' => 'Anda adalah analis lingkungan profesional. Jawab HANYA dalam format JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 2000,
            ]);

        if (!$response->successful()) {
            throw new \Exception("AI API error: {$response->status()} - {$response->body()}");
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }

    protected function parseAiResponse(string $response): ?array
    {
        $response = trim($response);

        if (str_starts_with($response, '```json')) {
            $response = preg_replace('/^```json\s*/', '', $response);
            $response = preg_replace('/\s*```$/', '', $response);
        } elseif (str_starts_with($response, '```')) {
            $response = preg_replace('/^```\s*/', '', $response);
            $response = preg_replace('/\s*```$/', '', $response);
        }

        $parsed = json_decode($response, true);

        if (!$parsed) {
            return null;
        }

        return [
            'categories' => $parsed['categories'] ?? [],
            'locations' => $parsed['locations'] ?? [],
            'cause' => $parsed['cause'] ?? null,
            'impact' => $parsed['impact'] ?? null,
            'severity' => min(100, max(0, (int) ($parsed['severity'] ?? 0))),
        ];
    }

    protected function storeAnalysis(Article $article, array $result): void
    {
        foreach ($result['categories'] as $categoryName) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => str($categoryName)->slug()]
            );
            $article->categories()->attach($category->id);
        }

        foreach ($result['locations'] as $locationData) {
            $location = Location::updateOrCreate(
                [
                    'name' => $locationData['name'],
                    'regency_name' => $locationData['regency'] ?? null,
                    'province_name' => $locationData['province'] ?? null,
                ],
                []
            );
            $article->locations()->attach($location->id);

            EnvironmentalCase::create([
                'article_id' => $article->id,
                'location_id' => $location->id,
                'category_id' => $article->categories()->first()?->id,
                'cause' => $result['cause'],
                'impact' => $result['impact'],
                'severity' => $result['severity'],
            ]);
        }
    }
}
