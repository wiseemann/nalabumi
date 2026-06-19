<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArticleScraperService
{
    protected array $uiPatterns = [
        '/^Lihat Semua Ide Utama$/i',
        '/^Baca Juga:/i',
        '/^Tags:/i',
        '/^Share:/i',
        '/^Komentar$/i',
        '/^Tinggalkan Balasan$/i',
        '/^Nama$/i',
        '/^Email$/i',
        '/^Situs Web$/i',
        '/^Simpan nama, email, dan situs web/i',
        '/^Berikutnya$/i',
        '/^Sebelumnya$/i',
        '/^Related Posts/i',
        '/^Artikel Terkait/i',
        '/^Berita Populer/i',
        '/^Berita Terbaru/i',
        '/^Trending/i',
        '/^Advertisement$/i',
        '/^Iklan$/i',
        '/^Komentar Facebook$/i',
        '/^Powered by/i',
        '/^©/i',
        '/^Copyright/i',
        '/^All rights reserved/i',
        '/^Hak cipta/i',
        '/^GTM Container/i',
        '/^Google Tag Manager/i',
        '/^End Google Tag Manager/i',
        '/^VideoPodcast/i',
        '/^Bahasa Indonesia/i',
        '/^हिंदी/i',
        '/^বাংলা/i',
        '/^Berita Singkat/i',
        '/^Cerita Fitur/i',
        '/^Dapatkan kisah/i',
        '/^Kami adalah media/i',
        '/^Dukung kami/i',
        '/^Jelajahi situs/i',
        '/^Berita dan Inspirasi/i',
        '/^Berita singkat/i',
        '/^Seribu Luka/i',
        '/^Waspada Kebakaran/i',
        '/^Judul Artikel/i',
        '/^SCROLL TO CONTINUE/i',
        '/^e:\s/i',
        '/^\/\d+\/CNN_/i',
        '/^\/\d+\/detik_/i',
    ];

    protected array $jsPatterns = [
        '/^googletag/i',
        '/^gtag/i',
        '/^adsbygoogle/i',
        '/^fbq\(/i',
        '/^_fbq/i',
        '/^twq\(/i',
        '/^ttq\(/i',
        '/^_ttq/i',
        '/^pintrk\(/i',
        '/^_pintrk/i',
        '/^_linkedin_/i',
        '/^_satellite/i',
        '/^_paq\.push/i',
        '/^_gaq\.push/i',
        '/^dataLayer/i',
        '/^gtm/i',
        '/^idkanal/i',
        '/^waitForElement/i',
        '/^classList/i',
        '/^console\.log/i',
        '/^function\s*\w*\s*\(/i',
        '/^const\s/i',
        '/^let\s/i',
        '/^var\s/i',
        '/^window\./i',
        '/^document\./i',
        '/^if\s*\(/i',
        '/^for\s*\(/i',
        '/^while\s*\(/i',
        '/^return\s/i',
        '/^\.push\(/i',
        '/^\.forEach\(/i',
        '/^\.classList/i',
        '/^\.style\./i',
        '/^\.innerHTML/i',
        '/^\.textContent/i',
        '/^callback:/i',
        '/^\/\/ /i',
        '/^\(function/i',
        '/^else\s*\{/i',
        '/^\.textContent/i',
        '/^\{/i',
        '/^\}/i',
        '/^;\s*$/i',
        '/^@\s/i',
    ];

    public function scrapeArticle(Article $article): bool
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'id,en-US;q=0.7,en;q=0.3',
                ])
                ->get($article->url);

            if (!$response->successful()) {
                Log::warning("Failed to scrape article {$article->id}: HTTP {$response->status()}");
                return false;
            }

            $html = $response->body();
            $content = $this->extractContent($html, $article->source);

            if (empty($content)) {
                Log::warning("No content extracted for article {$article->id}");
                return false;
            }

            $article->update([
                'content' => $content,
                'is_scraped' => true,
                'scraped_at' => now(),
            ]);

            Log::info("Successfully scraped article {$article->id}: {$article->title}");
            return true;

        } catch (\Exception $e) {
            Log::error("Error scraping article {$article->id}: {$e->getMessage()}");
            return false;
        }
    }

    protected function extractContent(string $html, string $source): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $xpath = new \DOMXPath($dom);

        $articleSelectors = [
            '//article',
            '//*[contains(@class, "article")]',
            '//*[contains(@class, "content")]',
            '//*[contains(@class, "post-body")]',
            '//*[contains(@class, "entry-content")]',
            '//*[contains(@class, "story__content")]',
            '//*[contains(@id, "content")]',
            '//div[@class="detail__body"]',
            '//div[contains(@class, "read-content")]',
        ];

        foreach ($articleSelectors as $selector) {
            $nodes = @$xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $content = $this->extractTextFromNode($nodes->item(0));
                if (strlen($content) > 200) {
                    return $this->cleanContent($content);
                }
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $content = $this->extractTextFromNode($body);
            return $this->cleanContent($content);
        }

        return '';
    }

    protected function extractTextFromNode(\DOMNode $node): string
    {
        if ($node->nodeType === XML_ELEMENT_NODE && in_array(strtolower($node->nodeName), ['script', 'style', 'noscript', 'iframe', 'nav', 'footer', 'header', 'aside', 'form', 'button', 'svg', 'img'])) {
            return '';
        }

        $text = '';

        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeName === 'p' || $child->nodeName === 'div' || $child->nodeName === 'br') {
                    $text .= $this->extractTextFromNode($child) . "\n";
                } else {
                    $text .= $this->extractTextFromNode($child);
                }
            }
        } else {
            $text = $node->textContent;
        }

        return $text;
    }

    protected function cleanContent(string $content): string
    {
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $cleanLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (empty($trimmed)) {
                continue;
            }

            if ($this->isJsLine($trimmed)) {
                continue;
            }

            if ($this->isUiPattern($trimmed)) {
                continue;
            }

            if (strlen($trimmed) >= 20) {
                $cleanLines[] = $trimmed;
            }
        }

        return implode("\n\n", $cleanLines);
    }

    protected function isJsLine(string $line): bool
    {
        foreach ($this->jsPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        return false;
    }

    protected function isUiPattern(string $line): bool
    {
        foreach ($this->uiPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        return false;
    }

    public function scrapeUnscrapedArticles(int $limit = 50): int
    {
        $articles = Article::where('is_scraped', false)
            ->whereNotNull('url')
            ->limit($limit)
            ->get();

        $scraped = 0;
        foreach ($articles as $article) {
            if ($this->scrapeArticle($article)) {
                $scraped++;
            }
        }

        return $scraped;
    }

    public function cleanExistingArticles(): int
    {
        $articles = Article::where('is_scraped', true)->get();
        $cleaned = 0;

        foreach ($articles as $article) {
            $newContent = $this->cleanContent($article->content);
            if ($newContent !== $article->content && strlen($newContent) > 100) {
                $article->content = $newContent;
                $article->save();
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
