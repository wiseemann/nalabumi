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

            // Auto-verification logic
            $is_verified = null; // null means pending
            $verified_at = null;
            
            if ($result['confidence'] >= 70) {
                if ($result['severity'] > 0) {
                    $is_verified = true; // High confidence environmental case -> Accept
                } else {
                    $is_verified = false; // High confidence NOT an environmental case -> Reject
                }
                $verified_at = now();
            }

            $article->update([
                'is_analyzed' => true,
                'analyzed_at' => now(),
                'is_verified' => $is_verified,
                'verified_at' => $verified_at,
            ]);

            Log::info("Successfully analyzed article {$article->id}");
            return $result;

        } catch (\Exception $e) {
            Log::error("AI analysis failed for article {$article->id}: {$e->getMessage()}");
            // Mark as analyzed but rejected so it doesn't get stuck forever
            $article->update([
                'is_analyzed' => true,
                'analyzed_at' => now(),
                'is_verified' => false,
                'verified_at' => now(),
            ]);
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
            "regency": "nama kabupaten/kota lengkap (contoh: Kabupaten Kutai Kartanegara, Kota Surabaya)",
            "province": "nama provinsi lengkap (contoh: Kalimantan Timur, Jawa Timur)"
        }
    ],
    "cause": "penyebab utama kerusakan lingkungan",
    "impact": "dampak yang ditimbulkan",
    "severity": 0-100,
    "confidence": 0-100
}

Kategori yang tersedia: {$categories}

PENTING:
- Pilih kategori yang sesuai dari daftar di atas (bisa lebih dari 1)
- Identifikasi lokasi spesifik yang disebutkan (kabupaten/kota, provinsi)
- Untuk regency, SELALU gunakan format lengkap: "Kabupaten ..." atau "Kota ..."
- Untuk province, gunakan nama resmi lengkap provinsi
- Berikan skor severity 0-100 berdasarkan tingkat keparahan kerusakan
- Berikan skor confidence 0-100 yang menunjukkan seberapa yakin Anda bahwa klasifikasi kategori, lokasi, dan severity sudah akurat berdasarkan isi berita
- Jika artikel menyebutkan lokasi secara eksplisit, confidence tinggi (70-100)
- Jika lokasi harus diperkirakan dari konteks, confidence sedang (40-69)
- Jika lokasi tidak jelas, confidence rendah (0-39)
- Jika tidak ada informasi tentang lingkungan, set severity = 0 dan confidence = 0
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
        
        // Extract JSON block using regex if there's surrounding text
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $response = $matches[0];
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
            'confidence' => min(100, max(0, (int) ($parsed['confidence'] ?? 0))),
        ];
    }

    protected function storeAnalysis(Article $article, array $result): void
    {
        foreach ($result['categories'] as $categoryName) {
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => str($categoryName)->slug()]
            );
            $article->categories()->syncWithoutDetaching([$category->id]);
        }

        $categoryId = $article->categories()->first()?->id;

        foreach ($result['locations'] as $locationData) {
            $location = Location::updateOrCreate(
                [
                    'name' => $locationData['name'],
                    'regency_name' => $locationData['regency'] ?? null,
                    'province_name' => $locationData['province'] ?? null,
                ],
                []
            );
            $article->locations()->syncWithoutDetaching([$location->id]);

            if ($categoryId) {
                EnvironmentalCase::create([
                    'article_id' => $article->id,
                    'location_id' => $location->id,
                    'category_id' => $categoryId,
                    'cause' => $result['cause'],
                    'impact' => $result['impact'],
                    'severity' => $result['severity'],
                    'confidence' => $result['confidence'] ?? 0,
                ]);
            }
        }
    }
}
