<?php

namespace App\Console\Commands\Word;

use App\Models\Word\EducationLevel;
use App\Models\Word\Word;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\DomCrawler\Crawler;

class FetchWordFromIcibaCommand extends Command
{
    protected $signature = 'word:fetch-iciba 
                            {--word= : 指定单词（多个用逗号分隔，如 circuit,bike）}
                            {--book= : 指定单词书ID（仅处理该书中的单词）}
                            {--limit=50 : 每次处理的单词数量}
                            {--sleep=800 : 每个请求之间的间隔(毫秒)}
                            {--force : 强制更新已有数据的单词}';

    protected $description = '从词典API获取单词的音标、中文释义和例句';

    private int $successCount = 0;

    private int $failCount = 0;

    public function handle(): int
    {
        $wordOption = $this->option('word');
        $bookId = $this->option('book');
        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');
        $force = $this->option('force');

        // 优先处理指定单词
        if (! empty($wordOption)) {
            return $this->handleSpecifiedWords(
                array_filter(array_map('trim', explode(',', $wordOption))),
                $sleep
            );
        }

        $query = Word::query();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('explanation')
                    ->orWhere('explanation', '')
                    ->orWhere('explanation', 'LIKE', '%【英】%');
            });
        }

        if ($bookId) {
            $query->whereHas('books', function ($q) use ($bookId) {
                $q->where('word_books.id', $bookId);
            });
        }

        $total = $query->count();
        $this->info("找到 {$total} 个需要更新的单词");

        if ($total === 0) {
            $this->info('所有单词数据已完整');

            return Command::SUCCESS;
        }

        $words = $query->with('books')->limit($limit)->get();
        $this->info("开始处理 {$words->count()} 个单词...");
        $this->newLine();

        foreach ($words as $index => $word) {
            $this->printDivider($index + 1, $words->count());
            $this->fetchAndUpdate($word);
            usleep($sleep * 1000);
        }

        $this->finishTip($total, $limit);

        return Command::SUCCESS;
    }

    /**
     * 处理通过 --word= 指定的单词（多个逗号分隔）
     */
    private function handleSpecifiedWords(array $contents, int $sleep): int
    {
        if (empty($contents)) {
            $this->warn('未提供有效单词，请使用 --word=单词1,单词2');

            return Command::FAILURE;
        }

        $lowerContents = array_map('strtolower', $contents);
        $query = Word::with('books')->where(function ($q) use ($lowerContents) {
            foreach ($lowerContents as $c) {
                $q->orWhereRaw('LOWER(content) = ?', [$c]);
            }
        });
        $words = $query->get();

        $found = $words->pluck('content')->map('strtolower')->all();
        $missing = array_diff($lowerContents, $found);
        if ($missing) {
            $this->warn('以下单词在词库中不存在，已跳过: ' . implode(', ', $missing));
        }

        if ($words->isEmpty()) {
            return Command::SUCCESS;
        }

        $this->info('指定单词 ' . $words->count() . ' 个，开始处理...');
        $this->newLine();

        foreach ($words as $index => $word) {
            $this->printDivider($index + 1, $words->count(), $word->content);
            $this->fetchAndUpdate($word);
            usleep($sleep * 1000);
        }

        $this->finishTip();

        return Command::SUCCESS;
    }

    /**
     * 核心方法：抓取单词数据并更新
     */
    private function fetchAndUpdate(Word $word): void
    {
        try {
            $phonetic = null;
            $zhMeaning = '';
            $enMeaning = '';
            $examples = [];
            $source = '';

            // 1. 优先尝试有道 API
            $youdaoData = $this->fetchFromYoudao($word->content);

            if ($youdaoData) {
                $phonetic = $youdaoData['phonetic'];
                $zhMeaning = $youdaoData['zh_meaning'];
                $examples = $youdaoData['examples'];
                $source = '有道';
            }

            // 2. 若 API 解析失败再尝试网页抓取
            if (empty($zhMeaning)) {
                $webData = $this->fetchMeaningFromYoudaoWeb($word->content);
                if ($webData) {
                    $phonetic = $phonetic ?: ($webData['phonetic'] ?? null);
                    $zhMeaning = $webData['zh_meaning'];
                    $source = '有道(网页)';
                }
            }

            // 有释义但无例句时，从有道网页补抓例句
            if (empty($examples) || ! empty($zhMeaning) || ! empty($phonetic)) {
                $examples = $this->fetchExamplesFromYoudaoWeb($word->content);
            }

            $hasChineseExamples = ! empty($examples) && $this->hasChineseExamples($examples);
            $hasMeaningOrPhonetic = (bool) ($zhMeaning || $phonetic);
            if (! $source && $hasChineseExamples) {
                $source = '有道(网页)';
            }

            if ($hasMeaningOrPhonetic || $hasChineseExamples) {
                // 有释义/音标则更新；仅有中文例句时也写入例句
                $newExamples = $word->example_sentences ?? [];
                if ($hasChineseExamples) {
                    $newExamples = $examples;
                }

                $word->update([
                    'phonetic_us' => $phonetic ?? $word->phonetic_us,
                    'explanation' => $zhMeaning ?: $word->explanation,
                    'example_sentences' => $newExamples,
                ]);
                $this->syncEducationLevels($word);

                $this->newLine();
                $this->info("✓ {$word->content} [{$source}]");
                if ($phonetic) {
                    $this->line("  音标: /{$phonetic}/");
                }
                $this->line('  释义: ' . ($zhMeaning ?: '（无）'));
                if (! empty($newExamples) && $this->hasChineseExamples($newExamples)) {
                    $this->line('  例句: ' . count($newExamples) . ' 条（含中文）');
                }
                $this->successCount++;
            } else {
                $this->newLine();
                $this->warn("✗ {$word->content} - 未找到数据");
                $this->line('  已尝试: 有道 API、有道(网页)');
                $this->line('  已获取音标: （无）');
                $this->line('  已获取释义: （无）');
                if (! empty($examples)) {
                    $hasZh = $this->hasChineseExamples($examples);
                    $this->line('  已获取例句: ' . count($examples) . ' 条（' . ($hasZh ? '含中文，但因释义/音标未获取未写入' : '无中文未采用') . '）');
                    foreach (array_slice($examples, 0, 5) as $i => $ex) {
                        $en = $ex['en'] ?? '';
                        $zh = $ex['zh'] ?? '';
                        $enShort = mb_strlen($en) > 60 ? mb_substr($en, 0, 60) . '…' : $en;
                        $zhShort = $zh !== '' ? (mb_strlen($zh) > 40 ? mb_substr($zh, 0, 40) . '…' : $zh) : '（空）';
                        $this->line('    ' . ($i + 1) . '. en: ' . $enShort);
                        $this->line('       zh: ' . $zhShort);
                    }
                }
                $this->echoWebDiagnostic($word->content);
                $this->failCount++;
            }
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("✗ {$word->content} - 异常: {$e->getMessage()}");
            Log::error("获取单词数据异常: {$word->content}", ['error' => $e->getMessage()]);
            $this->failCount++;
        }
    }

    private function fetchFromYoudao(string $word): ?array
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
                Log::warning("有道API单词不匹配: 请求 {$word}, 返回 {$input}");

                return null;
            }

            $wordData = $data['ec']['word'][0] ?? $data['simple']['word'][0] ?? null;
            if (! $wordData) {
                return null;
            }

            $returnPhrase = strtolower($wordData['return-phrase']['l']['i'] ?? '');
            if ($returnPhrase && $returnPhrase !== strtolower($word)) {
                Log::warning("有道API单词不匹配(二次): 请求 {$word}, 返回 {$returnPhrase}");

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
                $examples = $this->fetchExamplesFromYoudaoWeb($word) ?: [];
            }

            return [
                'phonetic' => $phonetic,
                'zh_meaning' => $zhMeaning,
                'examples' => $examples,
            ];
        } catch (\Throwable $e) {
            // log略
            return null;
        }
    }

    /**
     * 从有道网页获取例句（使用 DomCrawler 解析）
     */
    private function fetchExamplesFromYoudaoWeb(string $word): array
    {
        try {
            $html = $this->fetchYoudaoResultHtml($word);
            if ($html === null) {
                return [];
            }

            $crawler = $this->createCrawler($html);
            $examples = [];

            // blng_sents_part：.sen-eng + .sen-ch（与页面源码一致）
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

    /**
     * 请求有道结果页 HTML（释义与例句共用，避免重复请求）
     */
    private function fetchYoudaoResultHtml(string $word): ?string
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
     * 有道 API 失败时，从有道结果页抓取简明释义和音标（使用 DomCrawler，与网易词典一致）
     */
    private function fetchMeaningFromYoudaoWeb(string $word): ?array
    {
        try {
            $html = $this->fetchYoudaoResultHtml($word);
            if ($html === null) {
                return null;
            }

            if (stripos($html, 'word=' . urlencode($word)) === false && ! preg_match('/' . preg_quote($word, '/') . '/i', $html)) {
                return null;
            }

            $crawler = $this->createCrawler($html);
            $phonetic = $this->extractPhoneticFromCrawler($crawler);
            if ($phonetic === null) {
                $phonetic = $this->extractPhoneticFromHtml($html);
            }

            $zhMeaningParts = $this->extractDefinitionsFromCrawler($crawler);
            if (empty($zhMeaningParts)) {
                $zhMeaningParts = $this->extractDefinitionsFromHtml($html);
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
     * 未找到数据时输出网页诊断（便于区分请求失败与页面结构问题）
     */
    private function echoWebDiagnostic(string $word): void
    {
        try {
            $html = $this->fetchYoudaoResultHtml($word);
            if ($html === null) {
                $this->line('  诊断: 有道网页请求失败或超时');

                return;
            }
            if (stripos($html, $word) === false && stripos($html, 'word=' . urlencode($word)) === false) {
                $this->line('  诊断: 网页已返回，但内容中未包含该词');

                return;
            }
            $crawler = $this->createCrawler($html);
            $phoneticCount = $crawler->filter('.phonetic')->count();
            $perPhoneCount = $crawler->filter('.per-phone')->count();
            $senEngCount = $crawler->filter('.sen-eng')->count();
            $this->line("  诊断: 网页已获取，页面内 .phonetic={$phoneticCount} .per-phone={$perPhoneCount} .sen-eng={$senEngCount}（若均为 0 可能页面结构已变化）");
        } catch (\Throwable $e) {
            $this->line('  诊断: 检查时异常 - ' . $e->getMessage());
        }
    }

    /**
     * 创建 Crawler 并指定 UTF-8，避免音标等特殊字符解析错误
     */
    private function createCrawler(string $html): Crawler
    {
        $crawler = new Crawler;
        $crawler->addHtmlContent($html, 'UTF-8');

        return $crawler;
    }

    /**
     * 从原始 HTML 用正则提取音标（兜底，与页面 class="phonetic" 一致）
     */
    private function extractPhoneticFromHtml(string $html): ?string
    {
        // 美音优先：美</span> 后出现的 class="phonetic">/ xxx /
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
     * 使用 DomCrawler 从页面中提取音标（美音优先，与 .per-phone + .phonetic 结构一致）
     */
    private function extractPhoneticFromCrawler(Crawler $crawler): ?string
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
     * 使用 DomCrawler 从页面提取释义（.pos + .trans，与有道简明结构一致）
     */
    private function extractDefinitionsFromCrawler(Crawler $crawler): array
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
     * 从原始 HTML 用正则提取释义（兜底）
     */
    private function extractDefinitionsFromHtml(string $html): array
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
     * 自动根据单词书关联教育级别
     */
    private function syncEducationLevels(Word $word): void
    {
        if (! Schema::hasTable('word_education_levels')) {
            return;
        }

        try {
            // 加载所有级别 id
            $word->load('books.educationLevels');
            $levelIds = $word->books->flatMap(function ($book) {
                return $book->educationLevels->pluck('id');
            })->unique()->values()->all();

            if ($levelIds) {
                $word->educationLevels()->sync($levelIds);
                $levelNames = EducationLevel::whereIn('id', $levelIds)->pluck('name')->all();
                $this->line('  教育级别: ' . implode(', ', $levelNames));
            } else {
                $this->line('  教育级别: 无（小学或未匹配）');
            }
        } catch (\Throwable $e) {
            $this->warn("  警告: 关联教育级别失败: {$e->getMessage()}");
            Log::warning("关联教育级别失败: {$word->content}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * 判断例句列表中是否有中文翻译
     */
    private function hasChineseExamples(array $examples): bool
    {
        foreach ($examples as $ex) {
            if (! empty($ex['zh'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 进度分隔线
     */
    private function printDivider($current, $total, $word = null)
    {
        $this->line('─────────────────────────────────────');
        $msg = "[{$current}/{$total}]";
        if ($word) {
            $msg .= " {$word}";
        }
        $this->line($msg);
    }

    /**
     * 统一结果输出
     */
    private function finishTip($total = null, $limit = null)
    {
        $this->newLine();
        $this->info("完成! 成功: {$this->successCount}, 失败: {$this->failCount}");
        if ($total && $limit && $total > $limit) {
            $remaining = $total - $limit;
            $this->warn("还有 {$remaining} 个单词待处理，请再次运行此命令");
        }
    }
}
