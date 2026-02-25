<?php

namespace App\Console\Commands\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\UserSetting;
use App\Models\Word\UserWord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddFeb5LearningRecords extends Command
{
    /**
     * 命令名称及参数配置
     *
     * @var string
     */
    protected $signature = 'word:add-feb5-records 
                            {--user-id= : 指定用户ID，不指定则使用第一个用户}
                            {--count=10 : 要添加的学习记录数量，默认10个}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '添加2月5日学习过的单词记录，设置下次复习时间为今天（6号）';

    /**
     * 执行控制台命令
     */
    public function handle(): int
    {
        $userId = $this->option('user-id');
        $count = (int) $this->option('count');

        // 获取用户
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("用户 ID {$userId} 不存在");

                return Command::FAILURE;
            }
        } else {
            $user = User::first();
            if (! $user) {
                $this->error('数据库中没有用户，请先创建用户');

                return Command::FAILURE;
            }
        }

        $this->info("使用用户: {$user->name} (ID: {$user->id})");

        // 获取用户设置和当前单词书
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        if (! $setting->current_book_id) {
            // 如果没有选择单词书，选择第一个单词书
            $book = Book::first();
            if (! $book) {
                $this->error('数据库中没有单词书，请先导入单词数据');

                return Command::FAILURE;
            }
            $setting->current_book_id = $book->id;
            $setting->save();
            $this->info("自动选择单词书: {$book->name} (ID: {$book->id})");
        } else {
            $book = Book::findOrFail($setting->current_book_id);
            $this->info("使用单词书: {$book->name} (ID: {$book->id})");
        }

        // 获取该单词书中用户还未学习的单词
        $learnedWordIds = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->pluck('word_id');

        $availableWords = $book->words()
            ->whereNotIn('words.id', $learnedWordIds)
            ->limit($count)
            ->get();

        if ($availableWords->isEmpty()) {
            $this->warn("单词书 '{$book->name}' 中没有可用的新单词了");
            $this->info('尝试使用已学习的单词...');

            // 使用已学习的单词，但更新为5号学习记录
            $existingWords = UserWord::where('user_id', $user->id)
                ->where('word_book_id', $book->id)
                ->with('word')
                ->limit($count)
                ->get();

            if ($existingWords->isEmpty()) {
                $this->error('没有可用的单词');

                return Command::FAILURE;
            }

            $this->info("找到 {$existingWords->count()} 个已学习的单词，将更新为5号学习记录");

            DB::beginTransaction();
            try {
                /** @var \App\Models\Word\UserWord $userWord */
                foreach ($existingWords as $userWord) {
                    $userWord->update([
                        'status' => 1, // 学习中
                        'stage' => 0, // 第一阶段
                        'ease_factor' => 2.50,
                        'review_count' => 1,
                        'correct_count' => 1,
                        'wrong_count' => 0,
                        'last_review_at' => Carbon::parse('2026-02-05 10:00:00'), // 5号学习
                        'next_review_at' => Carbon::parse('2026-02-06 00:00:00'), // 6号需要复习
                    ]);
                    $this->line("  ✓ 更新单词: {$userWord->word->content}");
                }
                DB::commit();
                $this->info("成功更新 {$existingWords->count()} 条学习记录！");

                return Command::SUCCESS;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('更新失败: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->info("找到 {$availableWords->count()} 个新单词，开始创建学习记录...");

        // 设置时间：5号学习，6号需要复习
        $lastReviewAt = Carbon::parse('2026-02-05 10:00:00');
        $nextReviewAt = Carbon::parse('2026-02-06 00:00:00');

        DB::beginTransaction();
        try {
            /** @var \App\Models\Word\Word $word */
            foreach ($availableWords as $word) {
                UserWord::create([
                    'user_id' => $user->id,
                    'word_id' => $word->id,
                    'word_book_id' => $book->id,
                    'status' => 1, // 学习中
                    'stage' => 0, // 第一阶段（根据艾宾浩斯算法，1天后复习）
                    'ease_factor' => 2.50, // 默认难度因子
                    'review_count' => 1, // 已复习1次
                    'correct_count' => 1, // 正确1次
                    'wrong_count' => 0,
                    'is_favorite' => false,
                    'last_review_at' => $lastReviewAt,
                    'next_review_at' => $nextReviewAt,
                ]);
                $this->line("  ✓ 创建记录: {$word->content}");
            }

            DB::commit();
            $this->info("成功创建 {$availableWords->count()} 条学习记录！");
            $this->info('这些单词将在今天（6号）出现在复习列表中');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('创建失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
