<?php

namespace App\Services\Word;

use App\Models\Word\UserWord;
use Carbon\Carbon;

class EbbinghausService
{
    /**
     * 复习间隔天数(8 个阶段)
     */
    private const INTERVALS = [1, 2, 4, 7, 15, 30, 60, 180];

    /**
     * 计算下次复习时间
     *
     * @param  UserWord  $userWord  用户单词记录
     * @param  bool  $remembered  是否记住
     * @return Carbon 下次复习时间
     */
    public function calculateNextReview(UserWord $userWord, bool $remembered): Carbon
    {
        $currentStage = $userWord->stage ?? 0;

        if ($remembered) {
            // 记住：进入下一阶段
            $newStage = min($currentStage + 1, count(self::INTERVALS) - 1);
        } else {
            // 忘记：回到上一阶段(至少为 0)
            $newStage = max($currentStage - 1, 0);
        }

        // 更新阶段
        $userWord->stage = $newStage;

        // 计算下次复习时间
        $days = self::INTERVALS[$newStage];

        return now()->addDays($days);
    }

    /**
     * 更新难度因子(SM-2 算法简化版)
     *
     * @param  UserWord  $userWord  用户单词记录
     * @param  bool  $remembered  是否记住
     * @return float 新的难度因子
     */
    public function updateEaseFactor(UserWord $userWord, bool $remembered): float
    {
        $currentEase = $userWord->ease_factor ?? 2.50;

        if ($remembered) {
            // 记住：增加难度因子(最高 3.0)
            $newEase = min($currentEase + 0.15, 3.0);
        } else {
            // 忘记：减少难度因子(最低 1.3)
            $newEase = max($currentEase - 0.2, 1.3);
        }

        $userWord->ease_factor = $newEase;

        return $newEase;
    }

    /**
     * 处理单词复习结果
     *
     * @param  UserWord  $userWord  用户单词记录
     * @param  bool  $remembered  是否记住
     * @return UserWord 更新后的用户单词记录
     */
    public function processReview(UserWord $userWord, bool $remembered): UserWord
    {
        // 更新复习次数
        $userWord->review_count = ($userWord->review_count ?? 0) + 1;

        // 更新正确/错误次数
        if ($remembered) {
            $userWord->correct_count = ($userWord->correct_count ?? 0) + 1;
        } else {
            $userWord->wrong_count = ($userWord->wrong_count ?? 0) + 1;
        }

        // 计算下次复习时间
        $userWord->next_review_at = $this->calculateNextReview($userWord, $remembered);

        // 更新难度因子
        $this->updateEaseFactor($userWord, $remembered);

        // 更新最后复习时间
        $userWord->last_review_at = now();

        // 更新状态
        if ($userWord->stage >= 7) {
            $userWord->status = 2; // 已掌握
        } elseif ($userWord->wrong_count >= 3) {
            $userWord->status = 3; // 困难词
        } else {
            $userWord->status = 1; // 学习中
        }

        return $userWord;
    }
}
