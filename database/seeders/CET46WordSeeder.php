<?php

namespace Database\Seeders;

use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CET46WordSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('开始导入单词数据 ...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 创建分类和单词书
        $categories = [
            ['name' => '小学英语', 'description' => '小学英语核心词汇', 'sort_order' => 1],
            ['name' => '初中英语', 'description' => '初中英语核心词汇', 'sort_order' => 2],
            ['name' => '高中英语', 'description' => '高中英语核心词汇', 'sort_order' => 3],
            ['name' => '英语四级', 'description' => '大学英语四级词汇', 'sort_order' => 4],
            ['name' => '英语六级', 'description' => '大学英语六级词汇', 'sort_order' => 5],
        ];

        foreach ($categories as $catData) {
            $category = Category::firstOrCreate(['name' => $catData['name']], $catData);

            $bookName = $catData['name'] . '词汇';
            $difficulty = $catData['sort_order'];

            $book = Book::firstOrCreate(
                ['name' => $bookName],
                [
                    'word_category_id' => $category->id,
                    'description' => $catData['description'],
                    'difficulty' => $difficulty,
                    'sort_order' => $catData['sort_order'],
                ]
            );

            $words = $this->getWordsForLevel($catData['name']);
            $this->importWords($book, $words, $catData['name']);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info('单词数据导入完成！');
    }

    private function importWords(Book $book, array $words, string $type): void
    {
        $this->command->info("正在导入{$type}单词 (" . count($words) . ' 个)...');

        // 获取该书已关联的单词
        $existingWordIds = $book->words()->pluck('words.id')->toArray();
        $existingContents = Word::whereIn('id', $existingWordIds)->pluck('content')->toArray();

        $count = 0;
        $wordIdsToAttach = [];

        foreach ($words as $wordData) {
            $content = $wordData['word'] ?? $wordData['content'] ?? '';
            if (empty($content)) {
                continue;
            }

            // 跳过已关联到该书的单词
            if (in_array($content, $existingContents)) {
                continue;
            }

            // 查找或创建单词(全局唯一)
            $word = Word::firstOrCreate(
                ['content' => $content],
                [
                    'phonetic_us' => $wordData['phonetic_us'] ?? null,
                    'explanation' => $wordData['meaning'] ?? '',
                    'example_sentences' => [],
                    'difficulty' => $book->difficulty,
                    'frequency' => 3,
                ]
            );

            $wordIdsToAttach[] = $word->id;
            $count++;
        }

        // 批量关联单词到书籍
        if (! empty($wordIdsToAttach)) {
            // 分批附加，避免一次性操作太多数据
            foreach (array_chunk($wordIdsToAttach, 500) as $chunk) {
                $book->words()->syncWithoutDetaching($chunk);
            }
        }

        $book->updateWordCount();
        $this->command->info("已导入 {$count} 个{$type}单词");
    }

    private function getWordsForLevel(string $level): array
    {
        return match ($level) {
            '小学英语' => $this->getPrimaryWords(),
            '初中英语' => $this->getJuniorWords(),
            '高中英语' => $this->getSeniorWords(),
            '英语四级' => $this->getCET4Words(),
            '英语六级' => $this->getCET6Words(),
            default => [],
        };
    }

    private function getPrimaryWords(): array
    {
        $path = __DIR__ . '/CET46WordData/primary.php';

        return file_exists($path) ? require $path : [];
    }

    private function getJuniorWords(): array
    {
        $path = __DIR__ . '/CET46WordData/junior.php';

        return file_exists($path) ? require $path : [];
    }

    private function getSeniorWords(): array
    {
        $path = __DIR__ . '/CET46WordData/senior.php';

        return file_exists($path) ? require $path : [];
    }

    private function getCET4Words(): array
    {
        $path = __DIR__ . '/CET46WordData/cet4.php';

        return file_exists($path) ? require $path : [];
    }

    private function getCET6Words(): array
    {
        $path = __DIR__ . '/CET46WordData/cet6.php';

        return file_exists($path) ? require $path : [];
    }
}
