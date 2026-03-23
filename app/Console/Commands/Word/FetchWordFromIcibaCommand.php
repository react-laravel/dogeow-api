<?php

namespace App\Console\Commands\Word;

use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use App\Models\Word\Word;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FetchWordFromIcibaCommand extends Command
{
    protected $signature = 'word:fetch-iciba
                            {--word= : 指定单词(多个用逗号分隔，如 circuit,bike)}
                            {--book= : 指定单词书 ID(仅处理该书中的单词)}
                            {--limit=50 : 每次处理的单词数量}
                            {--sleep=800 : 每个请求之间的间隔(毫秒)}
                            {--force : 强制更新已有数据的单词}';

    protected $description = '从词典 API 获取单词的音标、中文释义和例句';

    private int $successCount = 0;

    private int $failCount = 0;

    private ?WordFetcher $fetcher = null;

    private ?WordParser $parser = null;

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
        $this->info("开始处理 {$words->count()} 个单词 ...");
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
     * 处理指定单词
     */
    private function handleSpecifiedWords(array $contents, int $sleep): int
    {
        if (empty($contents)) {
            $this->warn('未提供有效单词，请使用 --word=单词 1,单词 2');

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

        $this->info('指定单词 ' . $words->count() . ' 个，开始处理 ...');
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
            $fetcher = $this->getFetcher();
            $parser = $this->getParser();

            $phonetic = null;
            $zhMeaning = '';
            $examples = [];
            $source = '';

            // 1. 优先尝试有道 API
            $youdaoData = $fetcher->fetchFromYoudao($word->content);

            if ($youdaoData) {
                $phonetic = $youdaoData['phonetic'];
                $zhMeaning = $youdaoData['zh_meaning'];
                $examples = $youdaoData['examples'];
                $source = '有道';
            }

            // 2. 若 API 解析失败再尝试网页抓取
            if (empty($zhMeaning)) {
                $webData = $fetcher->fetchMeaningFromYoudaoWeb($word->content);
                if ($webData) {
                    $phonetic = $phonetic ?: ($webData['phonetic'] ?? null);
                    $zhMeaning = $webData['zh_meaning'];
                    $source = '有道(网页)';
                }
            }

            // 有释义但无例句时，从有道网页补抓例句
            if (empty($examples) || ! empty($zhMeaning) || ! empty($phonetic)) {
                $examples = $fetcher->fetchExamplesFromYoudaoWeb($word->content);
            }

            $hasChineseExamples = ! empty($examples) && $this->hasChineseExamples($examples);
            $hasMeaningOrPhonetic = (bool) ($zhMeaning || $phonetic);
            if (! $source && $hasChineseExamples) {
                $source = '有道(网页)';
            }

            if ($hasMeaningOrPhonetic || $hasChineseExamples) {
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
                    $this->line("  音标：/{$phonetic}/");
                }
                $this->line('  释义: ' . ($zhMeaning ?: '(无)'));
                if (! empty($newExamples) && $this->hasChineseExamples($newExamples)) {
                    $this->line('  例句: ' . count($newExamples) . ' 条(含中文)');
                }
                $this->successCount++;
            } else {
                $this->newLine();
                $this->warn("✗ {$word->content} - 未找到数据");
                $this->line('  已尝试: 有道 API、有道(网页)');
                $this->line('  已获取音标: (无)');
                $this->line('  已获取释义: (无)');
                if (! empty($examples)) {
                    $hasZh = $this->hasChineseExamples($examples);
                    $this->line('  已获取例句: ' . count($examples) . ' 条(' . ($hasZh ? '含中文，但因释义/音标未获取未写入' : '无中文未采用') . ')');
                    foreach (array_slice($examples, 0, 5) as $i => $ex) {
                        $en = $ex['en'] ?? '';
                        $zh = $ex['zh'] ?? '';
                        $enShort = mb_strlen($en) > 60 ? mb_substr($en, 0, 60) . '…' : $en;
                        $zhShort = $zh !== '' ? (mb_strlen($zh) > 40 ? mb_substr($zh, 0, 40) . '…' : $zh) : '(空)';
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

    /**
     * 输出网页诊断
     */
    private function echoWebDiagnostic(string $word): void
    {
        try {
            $fetcher = $this->getFetcher();
            $html = $fetcher->fetchYoudaoResultHtml($word);
            if ($html === null) {
                $this->line('  诊断：有道网页请求失败或超时');

                return;
            }
            if (stripos($html, $word) === false && stripos($html, 'word=' . urlencode($word)) === false) {
                $this->line('  诊断：网页已返回，但内容中未包含该词');

                return;
            }
            $parser = $this->getParser();
            $crawler = $parser->createCrawler($html);
            $phoneticCount = $crawler->filter('.phonetic')->count();
            $perPhoneCount = $crawler->filter('.per-phone')->count();
            $senEngCount = $crawler->filter('.sen-eng')->count();
            $this->line("  诊断: 网页已获取，页面内 .phonetic={$phoneticCount} .per-phone={$perPhoneCount} .sen-eng={$senEngCount}(若均为 0 可能页面结构已变化)");
        } catch (\Throwable $e) {
            $this->line('  诊断：检查时异常 - ' . $e->getMessage());
        }
    }

    /**
     * 同步教育级别
     */
    private function syncEducationLevels(Word $word): void
    {
        if (! Schema::hasTable('word_education_levels')) {
            return;
        }

        try {
            $word->load('books.educationLevels');
            /** @var Collection<int, Book> $books */
            $books = $word->books;
            $levelIds = $books->flatMap(function (Book $book) {
                return $book->educationLevels->pluck('id');
            })->unique()->values()->all();

            if ($levelIds) {
                $word->educationLevels()->sync($levelIds);
                $levelNames = EducationLevel::whereIn('id', $levelIds)->pluck('name')->all();
                $this->line('  教育级别：' . implode(', ', $levelNames));
            } else {
                $this->line('  教育级别: 无(小学或未匹配)');
            }
        } catch (\Throwable $e) {
            $this->warn("  警告：关联教育级别失败: {$e->getMessage()}");
            Log::warning("关联教育级别失败: {$word->content}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * 判断是否有中文例句
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
     * 结果输出
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

    private function getFetcher(): WordFetcher
    {
        return $this->fetcher ??= new WordFetcher;
    }

    private function getParser(): WordParser
    {
        return $this->parser ??= new WordParser;
    }
}
