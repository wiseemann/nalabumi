<?php

namespace App\Services;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class RssFeedService
{
    /**
     * Environmental keywords to filter relevant articles from general news sources.
     */
    protected array $environmentalKeywords = [
        'perusakan lingkungan', 'kerusakan alam', 'deforestasi', 'pencemaran', 'polusi', 'limbah beracun',
        'tambang ilegal', 'galian c', 'illegal logging', 'pembalakan liar', 'kebakaran hutan', 'karhutla',
        'bencana ekologis', 'sampah plastik', 'limbah industri', 'reklamasi pesisir', 'pemutihan karang',
        'perburuan satwa', 'perdagangan satwa', 'satwa dilindungi', 'krisis iklim', 'pemanasan global',
        'emisi karbon', 'konflik agraria', 'perampasan tanah', 'izin lingkungan', 'amdal',
        'walhi', 'greenpeace', 'jatam', 'pembakaran lahan', 'alih fungsi lahan', 'degradasi hutan',
    ];

    /**
     * Sources that are specifically about environment — no keyword filtering needed.
     */
    protected array $environmentalSources = [
        'Mongabay Indonesia', 'Greenpeace Indonesia', 'WALHI',
        'Forest Watch Indonesia', 'Betahita', 'JATAM (Jaringan Advokasi Tambang)',
        'Auriga Nusantara', 'Jurno.id', 'Project Multatuli',
    ];

    protected array $feeds = [
        [
            'url' => 'https://www.mongabay.co.id/feed/',
            'source' => 'Mongabay Indonesia',
        ],
        [
            'url' => 'https://jurno.id/feed/',
            'source' => 'Jurno.id',
        ],
        [
            'url' => 'https://www.antaranews.com/rss/terkini.xml',
            'source' => 'Antara News',
        ],
        [
            'url' => 'https://www.cnnindonesia.com/nasional/rss',
            'source' => 'CNN Indonesia',
        ],
        [
            'url' => 'https://timesindonesia.co.id/rss',
            'source' => 'Times Indonesia',
        ],
        [
            'url' => 'https://www.jpnn.com/index.php?mib=rss',
            'source' => 'JPNN',
        ],
        [
            'url' => 'https://wartakota.tribunnews.com/rss',
            'source' => 'Warta Kota',
        ],
        [
            'url' => 'https://www.greenpeace.org/indonesia/feed/',
            'source' => 'Greenpeace Indonesia',
        ],
        [
            'url' => 'https://www.walhi.or.id/feed/',
            'source' => 'WALHI',
        ],
        [
            'url' => 'https://fwi.or.id/feed/',
            'source' => 'Forest Watch Indonesia',
        ],
        [
            'url' => 'https://betahita.id/feed/',
            'source' => 'Betahita',
        ],
        [
            'url' => 'https://projectmultatuli.org/feed/',
            'source' => 'Project Multatuli',
        ],
        [
            'url' => 'https://www.jatam.org/feed/',
            'source' => 'JATAM (Jaringan Advokasi Tambang)',
        ],
        [
            'url' => 'https://auriga.or.id/feed/',
            'source' => 'Auriga Nusantara',
        ],
        // Additional General News
        [
            'url' => 'https://www.kompas.com/getrss/nasional',
            'source' => 'Kompas',
        ],
        [
            'url' => 'http://rss.tempo.co/nasional',
            'source' => 'Tempo',
        ],
        [
            'url' => 'https://news.detik.com/berita/rss',
            'source' => 'Detik',
        ],
        [
            'url' => 'https://www.republika.co.id/rss/nasional/',
            'source' => 'Republika',
        ],
        [
            'url' => 'https://feed.liputan6.com/rss/news',
            'source' => 'Liputan6',
        ],
        [
            'url' => 'https://tirto.id/sitemap/r/google-discover',
            'source' => 'Tirto',
        ],
        [
            'url' => 'https://lapi.kumparan.com/v2.0/rss/',
            'source' => 'Kumparan',
        ],
    ];

    public function fetchAll(): array
    {
        $allArticles = [];

        foreach ($this->feeds as $i => $feed) {
            try {
                $articles = $this->fetchFeed($feed['url'], $feed['source']);

                // Filter general news sources for environmental relevance
                if (!in_array($feed['source'], $this->environmentalSources)) {
                    $before = count($articles);
                    $articles = array_filter($articles, fn($a) => $this->isEnvironmentalArticle($a));
                    $articles = array_values($articles);
                    Log::info("Filtered {$feed['source']}: {$before} → " . count($articles) . " environmental articles");
                }

                $allArticles = array_merge($allArticles, $articles);
                Log::info("Fetched " . count($articles) . " articles from {$feed['source']}");
            } catch (\Exception $e) {
                Log::error("Failed to fetch RSS from {$feed['source']}: {$e->getMessage()}");
            }

            // Rate limiting: sleep 500ms between feed requests to be respectful
            if ($i < count($this->feeds) - 1) {
                usleep(500_000);
            }
        }

        return $allArticles;
    }

    /**
     * Fetch historical news from Bing News RSS using specific keywords.
     */
    public function fetchHistoricalFromGoogle(int $days = 15): int
    {
        $keywords = [
            'deforestasi', 'tambang ilegal', 'karhutla', 'pencemaran lingkungan',
            'pembalakan liar', 'limbah beracun', 'sampah plastik', 'perburuan satwa',
            'reklamasi pesisir', 'kebakaran hutan', 'krisis iklim',
            'pencemaran sungai', 'limbah laut', 'kerusakan terumbu karang', 'penangkapan ikan ilegal',
            'galian c', 'konflik agraria', 'perampasan tanah', 'alih fungsi lahan',
            'bencana ekologis', 'kerusakan alam', 'penggundulan hutan', 'pencemaran udara',
            'tumpahan minyak', 'pabrik mencemari limbah'
        ];
        $allArticles = [];

        foreach ($keywords as $i => $keyword) {
            try {
                $query = urlencode($keyword);
                $url = "https://www.bing.com/news/search?q={$query}&format=rss&mkt=id-id";

                $articles = $this->fetchFeed($url, 'Bing News');
                $articles = array_filter($articles, fn($a) => $this->isEnvironmentalArticle($a));
                
                $allArticles = array_merge($allArticles, $articles);
                Log::info("Fetched " . count($articles) . " historical articles from Bing News for keyword: {$keyword}");
            } catch (\Exception $e) {
                Log::error("Failed to fetch historical RSS for {$keyword}: {$e->getMessage()}");
            }

            if ($i < count($keywords) - 1) {
                usleep(500_000); // 500ms delay
            }
        }

        $stored = $this->storeArticles($allArticles);
        
        // Dispatch scrape jobs for the new articles
        foreach ($stored as $article) {
            \App\Jobs\ScrapeArticleJob::dispatch($article);
        }
        
        return count($stored);
    }

    /**
     * Check if an article is likely about environmental issues.
     */
    protected function isEnvironmentalArticle(array $article): bool
    {
        $text = strtolower(
            ($article['title'] ?? '') . ' ' .
            ($article['description'] ?? '') . ' ' .
            ($article['content'] ?? '')
        );

        foreach ($this->environmentalKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function fetchFeed(string $url, string $source): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Nalabumi AI RSS Reader/1.0'])
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch RSS feed: {$response->status()}");
        }

        return $this->parseXml($response->body(), $source);
    }

    protected function parseXml(string $xmlContent, string $source): array
    {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            Log::warning("Failed to parse XML from {$source}");
            return [];
        }

        $articles = [];
        $items = $xml->channel->item ?? $xml->entry ?? [];

        foreach ($items as $item) {
            $articleData = $this->extractArticleData($item, $source);
            if ($articleData['url']) {
                $articles[] = $articleData;
            }
        }

        return $articles;
    }

    protected function extractArticleData(SimpleXMLElement $item, string $source): array
    {
        $url = (string) ($item->link ?? '');
        $title = (string) ($item->title ?? '');
        $description = (string) ($item->description ?? $item->summary ?? '');
        $pubDate = (string) ($item->pubDate ?? $item->published ?? $item->updated ?? '');

        $content = '';
        if (isset($item->children('http://purl.org/rss/1.0/modules/content/')->encoded)) {
            $content = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
        }

        $author = '';
        if (isset($item->author)) {
            $author = (string) $item->author;
        } elseif (isset($item->children('http://purl.org/dc/elements/1.1/')->creator)) {
            $author = (string) $item->children('http://purl.org/dc/elements/1.1/')->creator;
        }

        $imageUrl = '';
        if (isset($item->enclosure['url'])) {
            $imageUrl = (string) $item->enclosure['url'];
        } elseif (isset($item->children('http://search.yahoo.com/mrss/')->thumbnail['url'])) {
            $imageUrl = (string) $item->children('http://search.yahoo.com/mrss/')->thumbnail['url'];
        }

        return [
            'url' => $url,
            'title' => $this->cleanTitle($title),
            'description' => $this->cleanText($description),
            'content' => $this->cleanText($content),
            'published_at' => $this->parseDate($pubDate),
            'source' => $source,
            'author' => $author,
            'image_url' => $imageUrl,
        ];
    }

    protected function cleanTitle(string $title): string
    {
        return trim(strip_tags($title));
    }

    protected function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    protected function parseDate(string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$dateString}");
            return null;
        }
    }

    public function storeArticles(array $articles): array
    {
        $stored = [];
        $skipped = 0;

        foreach ($articles as $articleData) {
            if (empty($articleData['url'])) {
                continue;
            }

            // Check for duplicate title to prevent identical news with different URLs
            $existingTitle = Article::where('title', $articleData['title'])->first();
            if ($existingTitle && $existingTitle->url !== $articleData['url']) {
                $skipped++;
                continue;
            }

            $article = Article::updateOrCreate(
                ['url' => $articleData['url']],
                [
                    'title' => $articleData['title'],
                    'content' => $articleData['content'] ?: null,
                    'published_at' => $articleData['published_at'],
                    'source' => $articleData['source'],
                    'author' => $articleData['author'] ?: null,
                    'image_url' => $articleData['image_url'] ?: null,
                ]
            );

            if ($article->wasRecentlyCreated) {
                $stored[] = $article;
            } else {
                $skipped++;
            }
        }

        Log::info("Stored " . count($stored) . " new articles, skipped {$skipped} existing");
        return $stored;
    }
}
