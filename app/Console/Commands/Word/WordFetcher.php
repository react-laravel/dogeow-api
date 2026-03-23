<?php

namespace App\Console\Commands\Word;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 单词数据抓取器
 */
class WordFetcher
{
    /**
     * 从有道 API 获取单词数据
     */
    public function fetchFromYoudao(string $word): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://dict.youdao.com/jsonapi_s', [
                'q' => $word,
                'le' => 'en',
                'client' => 'mobile',
            ]);
            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $input = isset($data['input']) ? $data['input'] : '';
            if (strtolower($input) !== strtolower($word)) {
                Log::warning("有道 API 单词不匹配: 请求 {$word}, 返回 {$input}");

                return null;
            }

            $wordData = $data['ec']['word'][0] ?? $data['simple']['word'][0] ?? null;
            if (! $wordData) {
                return null;
            }

            $returnPhrase = strtolower($wordData['return-phrase']['l']['i'] ?? '');
            if ($returnPhrase && $returnPhrase !== strtolower($word)) {
                Log::warning("有道 API 单词不匹配(二次): 请求 {$word}, 返回 {$returnPhrase}");

                return null;
            }

            $phonetic = $wordData['usphone'] ?? $wordData['ukphone'] ?? null;

            $zhMeaningParts = [];
            foreach (array_slice($wordData['trs'] ?? [], 0, 5) as $tr) {
                $items = $tr['tr'][0]['l']['i'] ?? [];
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    if (! is_string($item) || ! trim($item)) {
                        continue;
                    }
                    $text = trim($item);
                    if (! preg_match('/^[a-z]+\.\s+/i', $text)) {
                        $pos = $tr['pos'] ?? '';
                        if ($pos) {
                            $text = $pos . '. ' . $text;
                        }
                    }
                    $zhMeaningParts[] = $text;
                }
            }
            $zhMeaning = implode("\n", array_unique($zhMeaningParts));
            if (empty($zhMeaning)) {
                return null;
            }

            // 优先双语例句
            $examples = [];
            foreach (array_slice($data['blng_sents_part']['sentence-pair'] ?? [], 0, 2) as $sent) {
                if (! empty($sent['sentence']) && ! empty($sent['sentence-translation'])) {
                    $examples[] = [
                        'en' => strip_tags($sent['sentence']),
                        'zh' => strip_tags($sent['sentence-translation']),
                    ];
                }
            }

            if (empty($examples)) {
                $examples = (new WordParser)->fetchExamplesFromYoudaoWeb($word) ?: [];
            }

            return [
                'phonetic' => $phonetic,
                'zh_meaning' => $zhMeaning,
                'examples' => $examples,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 请求有道结果页 HTML
     */
    public function fetchYoudaoResultHtml(string $word): ?string
    {
        $url = 'https://dict.youdao.com/result?word=' . urlencode($word) . '&lang=en';
        $response = Http::timeout(12)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->get($url);

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * 从有道网页获取释义
     */
    public function fetchMeaningFromYoudaoWeb(string $word): ?array
    {
        try {
            $html = $this->fetchYoudaoResultHtml($word);
            if ($html === null) {
                return null;
            }

            if (stripos($html, 'word=' . urlencode($word)) === false && ! preg_match('/' . preg_quote($word, '/') . '/i', $html)) {
                return null;
            }

            $parser = new WordParser;
            $crawler = $parser->createCrawler($html);
            $phonetic = $parser->extractPhoneticFromCrawler($crawler);
            if ($phonetic === null) {
                $phonetic = $parser->extractPhoneticFromHtml($html);
            }

            $zhMeaningParts = $parser->extractDefinitionsFromCrawler($crawler);
            if (empty($zhMeaningParts)) {
                $zhMeaningParts = $parser->extractDefinitionsFromHtml($html);
            }

            $zhMeaning = implode("\n", array_slice($zhMeaningParts, 0, 6));
            if (empty($zhMeaning)) {
                return null;
            }

            return [
                'phonetic' => $phonetic,
                'zh_meaning' => $zhMeaning,
            ];
        } catch (\Throwable $e) {
            Log::warning("从有道网页获取释义失败: {$word}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 从有道网页获取例句
     */
    public function fetchExamplesFromYoudaoWeb(string $word): array
    {
        try {
            $html = $this->fetchYoudaoResultHtml($word);
            if ($html === null) {
                return [];
            }

            $parser = new WordParser;
            $crawler = $parser->createCrawler($html);
            $examples = [];

            $engNodes = $crawler->filter('.sen-eng');
            $zhNodes = $crawler->filter('.sen-ch');
            $n = min($engNodes->count(), $zhNodes->count(), 3);
            for ($i = 0; $i < $n; $i++) {
                $en = trim($engNodes->eq($i)->text());
                $zh = trim($zhNodes->eq($i)->text());
                if ($en !== '' && $zh !== '') {
                    $examples[] = ['en' => $en, 'zh' => $zh];
                }
            }

            return $examples;
        } catch (\Throwable $e) {
            Log::warning("从有道网页获取例句失败: {$word}", ['error' => $e->getMessage()]);

            return [];
        }
    }
}
