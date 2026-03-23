<?php

namespace App\Console\Commands\Word;

use Symfony\Component\DomCrawler\Crawler;

/**
 * 单词数据解析器
 */
class WordParser
{
    /**
     * 创建 Crawler
     */
    public function createCrawler(string $html): Crawler
    {
        $crawler = new Crawler;
        $crawler->addHtmlContent($html, 'UTF-8');

        return $crawler;
    }

    /**
     * 从 Crawler 提取音标
     */
    public function extractPhoneticFromCrawler(Crawler $crawler): ?string
    {
        try {
            $perPhones = $crawler->filter('.per-phone');
            foreach ($perPhones as $domNode) {
                $block = new Crawler($domNode);
                if (stripos($block->text(), '美') !== false) {
                    $phoneticNode = $block->filter('.phonetic');
                    if ($phoneticNode->count() > 0) {
                        $text = trim($phoneticNode->text());

                        return trim($text, "/ \t\n\r");
                    }
                }
            }
            $phoneticNodes = $crawler->filter('.phonetic');
            if ($phoneticNodes->count() > 0) {
                $text = trim($phoneticNodes->last()->text());

                return trim($text, "/ \t\n\r");
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * 从 HTML 提取音标
     */
    public function extractPhoneticFromHtml(string $html): ?string
    {
        if (preg_match('/美[\s\S]*?class="phonetic"[^>]*>\/\s*([^\/]+?)\s*\//u', $html, $m)) {
            return trim($m[1], "/ \t\n\r");
        }
        if (preg_match('/class="phonetic"[^>]*>\/\s*([^\/]+?)\s*\//u', $html, $m)) {
            return trim($m[1], "/ \t\n\r");
        }
        if (preg_match('/class="phonetic"[^>]*>([^<]+)</u', $html, $m)) {
            return trim($m[1], "/ \t\n\r");
        }

        return null;
    }

    /**
     * 从 Crawler 提取释义
     */
    public function extractDefinitionsFromCrawler(Crawler $crawler): array
    {
        $parts = [];
        try {
            $posNodes = $crawler->filter('.word-exp .pos');
            $transNodes = $crawler->filter('.word-exp .trans');
            $n = min($posNodes->count(), $transNodes->count(), 8);
            for ($i = 0; $i < $n; $i++) {
                $pos = trim($posNodes->eq($i)->text());
                $trans = trim($transNodes->eq($i)->text());
                if ($pos !== '' && $trans !== '') {
                    $line = $pos . ' ' . $trans;
                    if (! in_array($line, $parts, true)) {
                        $parts[] = $line;
                    }
                }
            }
            if (! empty($parts)) {
                return $parts;
            }
            $transOnly = $crawler->filter('.word-exp .trans');
            for ($i = 0; $i < min($transOnly->count(), 6); $i++) {
                $trans = trim($transOnly->eq($i)->text());
                if ($trans !== '') {
                    $parts[] = $trans;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $parts;
    }

    /**
     * 从 HTML 提取释义
     */
    public function extractDefinitionsFromHtml(string $html): array
    {
        $parts = [];
        if (preg_match_all('/(?:^|>)\s*([nvas]\.|名词|动词|形容词|副词|介词|代词|数词|量词|连词|叹词|冠词)\s*[.．]?\s*([^<]{2,}?)(?=<|$)/u', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $pos = trim($m[1]);
                $def = trim(preg_replace('/\s+/', ' ', $m[2]));
                if (mb_strlen($def) < 500) {
                    $line = $pos . ' ' . $def;
                    if (! in_array($line, $parts, true)) {
                        $parts[] = $line;
                    }
                }
            }
        }
        if (empty($parts) && preg_match_all('/([nvas]\.)\s*([^<]+?)(?:<|$)/u', $html, $matches, PREG_SET_ORDER)) {
            foreach (array_slice($matches, 0, 12) as $m) {
                $def = trim(preg_replace('/\s+/', ' ', $m[2]));
                if (mb_strlen($def) < 500) {
                    $line = $m[1] . ' ' . $def;
                    if (! in_array($line, $parts, true)) {
                        $parts[] = $line;
                    }
                }
            }
        }

        return $parts;
    }

    /**
     * 从有道网页获取例句
     */
    public function fetchExamplesFromYoudaoWeb(string $word): array
    {
        $fetcher = new WordFetcher;

        return $fetcher->fetchExamplesFromYoudaoWeb($word);
    }
}
