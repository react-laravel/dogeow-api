<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Models\Word\CheckIn;
use App\Models\Word\UserWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CheckInController extends Controller
{
    /**
     * 打卡
     * 优先使用前端传来的 local_date（用户本地日期），避免服务端 UTC 导致跨日显示错误
     */
    public function checkIn(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = Auth::user();

        $localDate = $request->input('local_date');
        if ($localDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDate)) {
            $today = \Carbon\Carbon::parse($localDate)->toDateString();
            $todayStart = \Carbon\Carbon::parse($localDate)->startOfDay();
            $todayEnd = \Carbon\Carbon::parse($localDate)->endOfDay();
        } else {
            $today = now()->toDateString();
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
        }

        // 检查该日期是否已打卡
        $checkIn = CheckIn::where('user_id', $user->id)
            ->where('check_in_date', $today)
            ->first();

        if ($checkIn) {
            return response()->json([
                'message' => '今天已经打卡过了',
                'check_in' => $checkIn,
            ]);
        }

        // 统计该日期学习数据（按服务端时区统计 created_at/last_review_at，仅作参考）
        $newWordsCount = UserWord::where('user_id', $user->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('status', '!=', 0)
            ->count();

        $reviewWordsCount = UserWord::where('user_id', $user->id)
            ->whereBetween('last_review_at', [$todayStart, $todayEnd])
            ->where('status', '!=', 0)
            ->count();

        // 创建打卡记录
        $checkIn = CheckIn::create([
            'user_id' => $user->id,
            'check_in_date' => $today,
            'new_words_count' => $newWordsCount,
            'review_words_count' => $reviewWordsCount,
            'study_duration' => 0, // 前端可以传入学习时长
        ]);

        return response()->json([
            'message' => '打卡成功',
            'check_in' => $checkIn,
        ]);
    }

    /**
     * 获取打卡日历
     */
    public function getCalendar(int $year, int $month): JsonResponse
    {
        $user = Auth::user();

        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = now()->setYear($year)->setMonth($month)->endOfMonth();

        $checkIns = CheckIn::where('user_id', $user->id)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => \Carbon\Carbon::parse($item->check_in_date)->toDateString());

        // 生成该月所有日期的打卡状态
        $calendar = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $checkIn = $checkIns->get($dateStr);
            $calendar[] = [
                'date' => $dateStr,
                'checked' => $checkIns->has($dateStr),
                'new_words_count' => $checkIn !== null ? ($checkIn->new_words_count ?? 0) : 0,
                'review_words_count' => $checkIn !== null ? ($checkIn->review_words_count ?? 0) : 0,
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'calendar' => $calendar,
        ]);
    }

    /**
     * 获取整年打卡日历
     */
    public function getCalendarYear(int $year): JsonResponse
    {
        $user = Auth::user();

        $startDate = now()->setYear($year)->startOfYear();
        $endDate = now()->setYear($year)->endOfYear();

        $checkIns = CheckIn::where('user_id', $user->id)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => \Carbon\Carbon::parse($item->check_in_date)->toDateString());

        $calendar = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $checkIn = $checkIns->get($dateStr);
            $calendar[] = [
                'date' => $dateStr,
                'checked' => $checkIns->has($dateStr),
                'new_words_count' => $checkIn !== null ? ($checkIn->new_words_count ?? 0) : 0,
                'review_words_count' => $checkIn !== null ? ($checkIn->review_words_count ?? 0) : 0,
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'year' => $year,
            'calendar' => $calendar,
        ]);
    }

    /**
     * 获取最近 365 天的打卡日历（包含今天）
     */
    public function getCalendarLast365(): JsonResponse
    {
        $user = Auth::user();

        $endDate = now()->endOfDay();
        $startDate = now()->startOfDay()->subDays(364); // 共 365 天

        $checkIns = CheckIn::where('user_id', $user->id)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => \Carbon\Carbon::parse($item->check_in_date)->toDateString());

        $calendar = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $checkIn = $checkIns->get($dateStr);
            $calendar[] = [
                'date' => $dateStr,
                'checked' => $checkIns->has($dateStr),
                'new_words_count' => $checkIn !== null ? ($checkIn->new_words_count ?? 0) : 0,
                'review_words_count' => $checkIn !== null ? ($checkIn->review_words_count ?? 0) : 0,
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'calendar' => $calendar,
        ]);
    }

    /**
     * 获取统计数据
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();

        // 已打卡天数
        $checkInDays = CheckIn::where('user_id', $user->id)
            ->distinct('check_in_date')
            ->count('check_in_date');

        // 已学单词数
        $learnedWordsCount = UserWord::where('user_id', $user->id)
            ->where('status', '!=', 0)
            ->distinct('word_id')
            ->count('word_id');

        // 总单词数（当前学习的单词书）
        $setting = \App\Models\Word\UserSetting::where('user_id', $user->id)->first();
        $totalWords = 0;
        $progressPercentage = 0;

        if ($setting && $setting->current_book_id) {
            $book = \App\Models\Word\Book::find($setting->current_book_id);
            if ($book) {
                $totalWords = $book->total_words;
                $learnedInBook = UserWord::where('user_id', $user->id)
                    ->where('word_book_id', $setting->current_book_id)
                    ->where('status', '!=', 0)
                    ->distinct('word_id')
                    ->count('word_id');

                if ($totalWords > 0) {
                    $progressPercentage = round(($learnedInBook / $totalWords) * 100, 2);
                }
            }
        }

        // 今日是否已打卡
        $todayCheckedIn = CheckIn::where('user_id', $user->id)
            ->where('check_in_date', now()->toDateString())
            ->exists();

        return response()->json([
            'check_in_days' => $checkInDays,
            'learned_words_count' => $learnedWordsCount,
            'total_words' => $totalWords,
            'progress_percentage' => $progressPercentage,
            'today_checked_in' => $todayCheckedIn,
        ]);
    }
}
