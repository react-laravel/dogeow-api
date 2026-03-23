<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 重构 words 表为多对多关系
     * 1. 创建中间表 word_book_word
     * 2. 合并重复单词，保留数据最完整的记录
     * 3. 迁移关联关系到中间表
     * 4. 删除 words 表的 word_book_id 列
     */
    public function up(): void
    {
        // 1. 创建中间表
        Schema::create('word_book_word', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('word_book_id')->comment('单词书 ID');
            $table->unsignedBigInteger('word_id')->comment('单词 ID');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->unique(['word_book_id', 'word_id']);
            $table->index('word_book_id');
            $table->index('word_id');
        });

        // 2. 查找并合并重复单词
        // 按 content 分组，选择数据最完整的记录作为主记录
        $duplicates = DB::table('words')
            ->select('content', DB::raw('COUNT(*) as cnt'), DB::raw('GROUP_CONCAT(id) as ids'))
            ->groupBy('content')
            ->having('cnt', '>', 1)
            ->get();

        $wordMapping = []; // 旧 ID => 新 ID 的映射

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup->ids);

            // 获取所有重复的单词记录
            $words = DB::table('words')
                ->whereIn('id', $ids)
                ->get();

            // 选择数据最完整的记录(优先有中文释义的)
            $bestWord = null;
            $bestScore = -1;

            foreach ($words as $w) {
                $score = 0;

                // 有中文释义加分
                if (! empty($w->explanation) && ! str_starts_with($w->explanation ?? '', '【英】')) {
                    $score += 10;
                }
                // 有音标加分
                if (! empty($w->phonetic_us)) {
                    $score += 5;
                }
                // 有例句加分
                $examples = json_decode($w->example_sentences ?? '[]', true);
                if (! empty($examples)) {
                    $score += 3;
                    // 例句有中文加更多分
                    if (! empty($examples[0]['zh'])) {
                        $score += 5;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestWord = $w;
                }
            }

            // 记录映射关系
            foreach ($ids as $oldId) {
                if ($oldId != $bestWord->id) {
                    $wordMapping[$oldId] = $bestWord->id;
                }
            }
        }

        // 3. 迁移数据到中间表
        $existingWords = DB::table('words')->get();
        $pivotData = [];
        $processedPairs = [];

        foreach ($existingWords as $word) {
            // 如果是重复的单词，使用映射后的 ID
            $wordId = $wordMapping[$word->id] ?? $word->id;
            $bookId = $word->word_book_id;

            // 避免重复插入
            $pairKey = "{$bookId}-{$wordId}";
            if (! isset($processedPairs[$pairKey])) {
                $pivotData[] = [
                    'word_book_id' => $bookId,
                    'word_id' => $wordId,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $processedPairs[$pairKey] = true;
            }
        }

        // 批量插入中间表数据
        foreach (array_chunk($pivotData, 500) as $chunk) {
            DB::table('word_book_word')->insert($chunk);
        }

        // 4. 更新 user_words 表的 word_id 引用
        foreach ($wordMapping as $oldId => $newId) {
            DB::table('user_words')
                ->where('word_id', $oldId)
                ->update(['word_id' => $newId]);
        }

        // 5. 删除重复的单词记录
        if (! empty($wordMapping)) {
            DB::table('words')
                ->whereIn('id', array_keys($wordMapping))
                ->delete();
        }

        // 6. 删除 word_book_id 列
        Schema::table('words', function (Blueprint $table) {
            $table->dropIndex(['word_book_id']);
            $table->dropColumn('word_book_id');
        });

        // 7. 给 content 列添加唯一索引
        Schema::table('words', function (Blueprint $table) {
            $table->unique('content');
        });

        // 8. 更新单词书的 total_words 统计
        $books = DB::table('word_books')->get();
        foreach ($books as $book) {
            $count = DB::table('word_book_word')
                ->where('word_book_id', $book->id)
                ->count();
            DB::table('word_books')
                ->where('id', $book->id)
                ->update(['total_words' => $count]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. 恢复 word_book_id 列
        Schema::table('words', function (Blueprint $table) {
            $table->dropUnique(['content']);
        });

        Schema::table('words', function (Blueprint $table) {
            $table->unsignedBigInteger('word_book_id')->nullable()->after('id');
            $table->index('word_book_id');
        });

        // 2. 从中间表恢复数据(取第一个关联的 book_id)
        $pivotData = DB::table('word_book_word')
            ->select('word_id', DB::raw('MIN(word_book_id) as word_book_id'))
            ->groupBy('word_id')
            ->get();

        foreach ($pivotData as $pivot) {
            DB::table('words')
                ->where('id', $pivot->word_id)
                ->update(['word_book_id' => $pivot->word_book_id]);
        }

        // 3. 删除中间表
        Schema::dropIfExists('word_book_word');
    }
};
