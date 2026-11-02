<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeArticleJob;
use App\Services\AiAnalysisService;
use App\Services\ArticleReliabilityService;
use App\Services\ArticleScraperService;
use App\Services\EmbeddingService;
use App\Services\QdrantService;
use App\Services\RssFeedService;
use App\Services\UrgencyScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchTopicNewsCommand extends Command
{
    protected $signature = 'nalabumi:fetch-topic 
        {query : Kata kunci pencarian berita, contoh: "karhutla di kalimantan"}
        {--sync : Proses scraping, analisis AI, dan embedding secara langsung tanpa antrean queue}
        {--all-unanalyzed : Proses juga seluruh artikel di database yang belum dianalisis (is_analyzed=0)}';

    protected $description = 'Fetch news articles on-demand by a specific search query (Bing & Google News RSS)';

    public function handle(
        RssFeedService $rssFeedService,
        ArticleScraperService $scraperService,
        AiAnalysisService $aiService,
        EmbeddingService $embeddingService,
        QdrantService $qdrantService,
        ArticleReliabilityService $reliabilityService,
        UrgencyScoreService $urgencyService
    ): int {
        $query = $this->argument('query');
        $isSync = $this->option('sync');
        $includeAllUnanalyzed = $this->option('all-unanalyzed');

        $this->info("🌿 Nalabumi — Mencari berita terbaru dengan kata kunci: \"{$query}\"...");

        $storedArticles = $rssFeedService->fetchByTopic($query);

        if ($includeAllUnanalyzed) {
            $existing = \App\Models\Article::where('is_analyzed', false)->get()->all();
            $storedArticles = array_merge($storedArticles, $existing);
        }

        if (empty($storedArticles)) {
            $words = array_filter(explode(' ', $query), fn($w) => strlen($w) >= 3);
            $existingUnanalyzed = \App\Models\Article::where('is_analyzed', false)
                ->where(function($q) use ($words) {
                    foreach ($words as $w) {
                        $q->orWhere('title', 'like', "%{$w}%");
                    }
                })
                ->get();

            if ($existingUnanalyzed->isNotEmpty()) {
                $this->info("🔍 Ditemukan {$existingUnanalyzed->count()} artikel unanalyzed di database yang relevan dengan topik. Memproses...");
                $storedArticles = $existingUnanalyzed->all();
            } else {
                $this->warn("⚠️ Tidak ada artikel baru atau unanalyzed yang ditemukan.");
                return Command::SUCCESS;
            }
        }

        $this->info("💾 Total target proses: " . count($storedArticles) . " artikel.");

        if ($isSync) {
            $this->info("⚡ Memproses scraping, analisis AI, dan embedding secara sinkron (langsung)...");

            try {
                $qdrantService->ensureCollection();
            } catch (\Exception $e) {
                $this->warn("⚠️ Qdrant collection check skipped: {$e->getMessage()}");
            }

            $bar = $this->output->createProgressBar(count($storedArticles));
            $bar->start();

            $successCount = 0;

            foreach ($storedArticles as $article) {
                // 1. Scrape Content
                $scraped = $scraperService->scrapeArticle($article);
                if (!$scraped) {
                    $bar->advance();
                    continue;
                }

                // 2. AI Analysis
                $analysis = $aiService->analyzeArticle($article);
                if (!$analysis) {
                    $bar->advance();
                    continue;
                }

                // 3. Chunking & Qdrant Embedding (Graceful)
                try {
                    $chunkEmbeddings = $embeddingService->generateChunkedEmbeddings($article);
                    if (!empty($chunkEmbeddings)) {
                        $basePayload = [
                            'article_id' => $article->id,
                            'title' => $article->title,
                            'source' => $article->source,
                            'url' => $article->url,
                            'published_at' => $article->published_at?->toDateString(),
                            'categories' => $article->categories->pluck('name')->toArray(),
                            'locations' => $article->locations->map(fn($loc) => [
                                'name' => $loc->name,
                                'regency' => $loc->regency_name,
                                'province' => $loc->province_name,
                            ])->toArray(),
                        ];

                        $points = [];
                        foreach ($chunkEmbeddings as $chunk) {
                            $pointId = ((int) $article->id * 1000) + (int) $chunk['chunk_index'];
                            $points[] = [
                                'id' => $pointId,
                                'vector' => $chunk['vector'],
                                'payload' => array_merge($basePayload, [
                                    'chunk_index' => $chunk['chunk_index'],
                                    'chunk_text' => $chunk['text'],
                                ]),
                            ];
                        }
                        $qdrantService->upsertPoints($points);
                        $article->update(['has_embedding' => true]);
                    }
                } catch (\Exception $e) {
                    // Ollama/Embedding is optional for display
                }

                // 4. Reliability Analysis (Graceful)
                try {
                    $reliabilityService->analyzeReliability($article);
                } catch (\Exception $e) {
                    // Reliability check optional
                }

                $successCount++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // 5. Recalculate Urgency & Map Cache
            $this->info("🗺️ Menghitung ulang skor urgensi peta...");
            $urgencyService->calculateUrgencyScores();
            Cache::forget('dashboard_urgency_scores');
            Cache::increment('urgency_scores_version');

            $this->info("✅ Sukses memproses {$successCount} dari " . count($storedArticles) . " artikel ke dalam database & Map!");
        } else {
            $this->info("📬 Mengirim " . count($storedArticles) . " artikel ke antrean background queue...");
            foreach ($storedArticles as $article) {
                ScrapeArticleJob::dispatch($article);
            }
            $this->info("✅ Job berhasil dimasukkan ke antrean queue.");
        }

        return Command::SUCCESS;
    }
}

