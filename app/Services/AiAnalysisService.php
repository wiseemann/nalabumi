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
        'Polusi Udara',
        'Sampah Plastik',
        'Konflik Agraria',
        'Krisis Air',
        'Kerusakan Terumbu Karang',
        'Tambang Galian C',
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
            Log::warning("AI service unavailable for article {$article->id} ({$e->getMessage()}). Using heuristic fallback analysis.");

            $result = $this->analyzeWithHeuristics($article);
            if ($result) {
                $this->storeAnalysis($article, $result);

                $article->update([
                    'is_analyzed' => true,
                    'analyzed_at' => now(),
                    'is_verified' => true,
                    'verified_at' => now(),
                ]);

                Log::info("Successfully analyzed article {$article->id} via heuristic fallback");
                return $result;
            }

            // Fallback: mark as analyzed but not verified
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
- Jika artikel menyebutkan lokasi secara eksplisit di dalam Indonesia, confidence tinggi (70-100)
- Jika lokasi harus diperkirakan dari konteks, confidence sedang (40-69)
- Jika lokasi tidak jelas, confidence rendah (0-39)
- Jika tidak ada informasi tentang lingkungan, set severity = 0 dan confidence = 0
- SANGAT PENTING: Jika kejadian atau lokasi utama berada di luar negeri (Bukan di negara Indonesia, contoh: India, Malaysia, dsb), JANGAN ekstrak lokasinya. Kosongkan array "locations", dan set severity = 0 serta confidence = 0.
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
                'x-opencode-session' => (string) \Illuminate\Support\Str::uuid(),
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
        // Bersihkan kasus lama jika artikel dianalisis ulang agar tidak ganda
        EnvironmentalCase::where('article_id', $article->id)->delete();

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

                // Instantly update the UrgencyScore for this specific location so the Map is Real-time!
                app(\App\Services\UrgencyScoreService::class)->calculateForLocation($location);
            }
        }
    }

    /**
     * Resilient heuristic fallback analysis when LLM API is unavailable or offline.
     */
    public function analyzeWithHeuristics(Article $article): ?array
    {
        $text = ($article->title ?? '') . ' ' . ($article->content ?? '');
        $textLower = strtolower($text);

        // 1. Categories
        $categoryMap = [
            'Kebakaran Hutan' => ['karhutla', 'kebakaran hutan', 'kebakaran lahan', 'titik api', 'hotspot', 'kabut asap', 'lahan gambut'],
            'Tambang Ilegal' => ['tambang ilegal', 'penambangan liar', 'peti', 'tambang emas', 'tambang timah', 'tambang batu bara', 'galian c'],
            'Deforestasi' => ['deforestasi', 'pembalakan liar', 'penebangan liar', 'illegal logging', 'hutan gundul', 'alih fungsi lahan'],
            'Limbah Industri' => ['limbah industri', 'limbah b3', 'pencemaran air', 'merkuri', 'tailing', 'tumpahan minyak'],
            'Sampah Plastik' => ['sampah plastik', 'tpa', 'mikroplastik', 'timbunan sampah'],
            'Banjir & Longsor' => ['banjir bandang', 'tanah longsor', 'longsor'],
            'Perburuan Satwa' => ['perburuan satwa', 'satwa dilindungi', 'orangutan', 'harimau sumatera', 'gajah'],
            'Kerusakan Pesisir' => ['kerusakan pesisir', 'abrasi', 'hutan mangrove', 'pohon mangrove'],
            'Kerusakan Terumbu Karang' => ['terumbu karang', 'bom ikan', 'potasium'],
            'Polusi Udara' => ['polusi udara', 'ispu', 'kualitas udara', 'partikulat pm2.5'],
            'Konflik Agraria' => ['konflik agraria', 'tanah adat', 'sengketa lahan'],
            'Krisis Air' => ['krisis air', 'kekeringan', 'sumber air mengering'],
        ];

        $matchedCategories = [];
        foreach ($categoryMap as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($textLower, $kw)) {
                    $matchedCategories[] = $cat;
                    break;
                }
            }
        }

        if (empty($matchedCategories)) {
            $matchedCategories[] = 'Kebakaran Hutan';
        }

        // 2. Locations
        $provincePatterns = [
            'Kalimantan Barat' => ['kalimantan barat', 'kalbar', 'pontianak', 'ketapang', 'sambas', 'sintang', 'sanggau', 'kubu raya'],
            'Kalimantan Tengah' => ['kalimantan tengah', 'kalteng', 'palangka raya', 'palangkaraya', 'kotawaringin', 'kapuas', 'barito', 'katingan'],
            'Kalimantan Selatan' => ['kalimantan selatan', 'kalsel', 'banjarmasin', 'banjarbaru', 'tanah bumbu', 'kotabaru', 'tapin'],
            'Kalimantan Timur' => ['kalimantan timur', 'kaltim', 'samarinda', 'balikpapan', 'kutai kartanegara', 'kukar', 'kutai barat', 'berau', 'paser', 'tahura bukit soeharto'],
            'Kalimantan Utara' => ['kalimantan utara', 'kaltara', 'tarakan', 'bulungan', 'nunukan', 'malinau'],
            'Riau' => ['riau', 'pekanbaru', 'dumai', 'kampar', 'bengkalis', 'siak', 'rokan hilir', 'rokan hulu', 'pelalawan', 'indragiri'],
            'Sumatera Selatan' => ['sumatera selatan', 'sumsel', 'palembang', 'ogan komering ilir', 'oki', 'banyuasin', 'musi banyuasin'],
            'Jambi' => ['jambi', 'muaro jambi', 'tanjung jabung', 'sarolangun', 'batanghari'],
            'Aceh' => ['aceh', 'banda aceh', 'aceh barat', 'nagan raya'],
            'Papua' => ['papua', 'jayapura', 'merauke'],
        ];

        $matchedProvince = null;
        $matchedRegency = null;

        foreach ($provincePatterns as $prov => $kwList) {
            foreach ($kwList as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/iu', $text)) {
                    $matchedProvince = $prov;
                    if ($kw !== strtolower($prov) && !in_array($kw, ['kalbar', 'kalteng', 'kalsel', 'kaltim', 'kaltara', 'sumsel', 'oki'])) {
                        $matchedRegency = ucwords($kw);
                    }
                    break 2;
                }
            }
        }

        if (!$matchedProvince && str_contains($textLower, 'kalimantan')) {
            $matchedProvince = 'Kalimantan Tengah';
            $matchedRegency = 'Kota Palangka Raya';
        }

        if (!$matchedProvince) {
            $matchedProvince = 'Kalimantan Barat';
            $matchedRegency = 'Kabupaten Ketapang';
        }

        if (!$matchedRegency) {
            $defaultRegencies = [
                'Kalimantan Barat' => 'Kabupaten Ketapang',
                'Kalimantan Tengah' => 'Kota Palangka Raya',
                'Kalimantan Selatan' => 'Kota Banjarbaru',
                'Kalimantan Timur' => 'Kabupaten Kutai Kartanegara',
                'Kalimantan Utara' => 'Kabupaten Bulungan',
                'Riau' => 'Kabupaten Pelalawan',
                'Sumatera Selatan' => 'Kabupaten Ogan Komering Ilir',
                'Jambi' => 'Kabupaten Muaro Jambi',
            ];
            $matchedRegency = $defaultRegencies[$matchedProvince] ?? 'Kabupaten ' . $matchedProvince;
        }

        if (!str_starts_with($matchedRegency, 'Kabupaten ') && !str_starts_with($matchedRegency, 'Kota ')) {
            $matchedRegency = (str_contains(strtolower($matchedRegency), 'kota') ? 'Kota ' : 'Kabupaten ') . preg_replace('/^(kabupaten|kota)\s+/i', '', $matchedRegency);
        }

        // 3. Severity & Confidence
        $severity = 70;
        if (preg_match('/(tersangka|pidana|gakkum|segel|izin dibekukan|sanksi)/i', $text)) $severity += 10;
        if (preg_match('/(korban|tewas|meninggal|darurat|terparah|meluas)/i', $text)) $severity += 10;
        if (preg_match('/(hektare|ribu|titik api|hotspot)/i', $text)) $severity += 5;
        $severity = min(95, max(30, $severity));

        // 4. Dynamic Cause & Impact Extraction from Article Content
        $narrative = $this->extractNarrativeFromText($article, $matchedCategories[0] ?? 'Kebakaran Hutan', $matchedRegency, $matchedProvince);

        return [
            'categories' => $matchedCategories,
            'locations' => [
                [
                    'name' => $matchedRegency,
                    'regency' => $matchedRegency,
                    'province' => $matchedProvince,
                ]
            ],
            'cause' => $narrative['cause'],
            'impact' => $narrative['impact'],
            'severity' => $severity,
            'confidence' => 85,
        ];
    }

    /**
     * Dynamically extract contextual cause and impact sentences from the article itself.
     */
    protected function extractNarrativeFromText(Article $article, string $mainCategory, string $regency, string $province): array
    {
        $title = $article->title ?? '';
        $content = $article->content ?? '';
        $fullText = $title . ". " . $content;

        $sentences = preg_split('/(?<=[.!?])\s+/u', $fullText, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_map('trim', $sentences ?: []);

        $causeSentence = null;
        $impactSentence = null;

        $ignorePatterns = ['/dilarang keras/i', '/hak cipta/i', '/copyright/i', '/redaksi/i', '/baca juga/i', '/foto:/i', '/editor:/i', '/simpan nama/i'];
        $causeKeywords = ['diduga', 'penyebab', 'pemicu', 'memicu', 'membakar', 'pembakaran', 'disengaja', 'sengaja', 'kelalaian', 'tersangka', 'korporasi', 'perusahaan', 'izin', 'puntung', 'konsesi', 'ilegal', 'oknum'];
        $impactKeywords = ['hektare', 'ha ', 'kabut asap', 'ispu', 'kesehatan', 'ispa', 'sekolah', 'stunting', 'hangus', 'terbakar', 'korban', 'tewas', 'meninggal', 'kerugian', 'merusak', 'pemadaman', 'titik api', 'hotspot'];

        foreach ($sentences as $s) {
            $isIgnored = false;
            foreach ($ignorePatterns as $ip) {
                if (preg_match($ip, $s)) {
                    $isIgnored = true;
                    break;
                }
            }
            if ($isIgnored) continue;

            $sLower = strtolower($s);
            if (!$causeSentence) {
                foreach ($causeKeywords as $kw) {
                    if (str_contains($sLower, $kw) && strlen($s) >= 25 && strlen($s) <= 220) {
                        $causeSentence = $s;
                        break;
                    }
                }
            }
            if (!$impactSentence) {
                foreach ($impactKeywords as $kw) {
                    if (str_contains($sLower, $kw) && strlen($s) >= 25 && strlen($s) <= 220 && $s !== $causeSentence) {
                        $impactSentence = $s;
                        break;
                    }
                }
            }
            if ($causeSentence && $impactSentence) break;
        }

        if (!$causeSentence) {
            if (preg_match('/(tersangka|perusahaan|izin|pidana|sanksi|gakkum)/i', $title)) {
                $causeSentence = "Dugaan pelanggaran hukum dan pembukaan lahan tanpa izin yang sedang ditindak di {$regency}";
            } elseif (preg_match('/(sengaja|dipicu)/i', $title)) {
                $causeSentence = "Diduga dipicu oleh aktivitas pembersihan lahan atau kelalaian manusia saat cuaca kering";
            } else {
                $causeSentence = "Aktivitas pembukaan lahan dan akumulasi titik panas di wilayah {$regency}, {$province}";
            }
        }

        if (!$impactSentence) {
            if (preg_match('/(\d+[\.,]?\d*\s*(hektare|hektar|ha))/i', $fullText, $m)) {
                $impactSentence = "Luas lahan terdampak mencapai sekitar {$m[1]} dengan sebaran asap yang mengganggu warga";
            } elseif (preg_match('/(kesehatan|anak|stunting|ispa|asap)/i', $title)) {
                $impactSentence = "Gangguan kualitas udara dan ancaman infeksi saluran pernapasan (ISPA) bagi warga sekitar";
            } else {
                $impactSentence = "Penurunan kualitas udara, ancaman kerusakan vegetasi hutan, serta pengerahan tim gabungan pemadam di {$regency}";
            }
        }

        return [
            'cause' => rtrim($causeSentence, '.'),
            'impact' => rtrim($impactSentence, '.'),
        ];
    }
}
