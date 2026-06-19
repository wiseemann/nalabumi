<?php

namespace App\Services;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class RssFeedService
{
    protected array $feeds = [
        [
            'url' => 'https://www.mongabay.co.id/feed/',
            'source' => 'Mongabay Indonesia',
        ],
        [
            'url' => 'https://www.republika.co.id/rss',
            'source' => 'Republika',
        ],
        [
            'url' => 'https://www.cnnindonesia.com/nasional/rss',
            'source' => 'CNN Indonesia Nasional',
        ],
        [
            'url' => 'https://www.antaranews.com/rss/terkini.xml',
            'source' => 'Antara Terkini',
        ],
        [
            'url' => 'https://www.tempo.co/rss/nasional',
            'source' => 'Tempo Nasional',
        ],
    ];

    public function fetchAll(): array
    {
        $allArticles = [];

        foreach ($this->feeds as $feed) {
            try {
                $articles = $this->fetchFeed($feed['url'], $feed['source']);
                $allArticles = array_merge($allArticles, $articles);
                Log::info("Fetched " . count($articles) . " articles from {$feed['source']}");
            } catch (\Exception $e) {
                Log::error("Failed to fetch RSS from {$feed['source']}: {$e->getMessage()}");
            }
        }

        return $allArticles;
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
