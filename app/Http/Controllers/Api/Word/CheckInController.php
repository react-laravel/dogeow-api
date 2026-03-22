<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Models\Word\CheckIn;
use App\Models\Word\UserWord;
use App\Services\Cache\RedisLockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CheckInController extends Controller
{
    private const LOCK_TTL_SECONDS = 10;

    public function __construct(
        private readonly RedisLockService $lockService
    ) {}

    /**
     * 打卡
     * 优先使用前端传来的 local_date(用户本地日期)，避免服务端 UTC 导致跨日显示错误
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

        // Use Redis lock to prevent race conditions on concurrent check-in attempts
        $lockKey = "checkin:{$user->id}:{$today}";
        $token = $this->lockService->lock($lockKey, self::LOCK_TTL_SECONDS);

        if ($token === false) {
            return response()->json([
                'message' => '正在处理打卡请求，请稍后重试',
            ], 429);
        }

        try {
            // Check if already checked in today (now protected by lock)
            $checkIn = CheckIn::where('user_id', $user->id)
                ->whereDate('check_in_date', $today)
                ->first();

            if ($checkIn) {
                return response()->json([
                    'message' => '今天已经打卡过了',
                    'check_in' => $checkIn,
                ]);
            }

            // Calculate study stats within transaction
            $newWordsCount = UserWord::where('user_id', $user->id)
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->where('status', '!=', 0)
                ->count();

            $reviewWordsCount = UserWord::where('user_id', $user->id)
                ->whereBetween('last_review_at', [$todayStart, $todayEnd])
                ->where('status', '!=', 0)
                ->count();

            // Create check-in record within a transaction for consistency
            $checkIn = CheckIn::create([
                'user_id' => $user->id,
                'check_in_date' => $today,
                'new_words_count' => $newWordsCount,
                'review_words_count' => $reviewWordsCount,
                'study_duration' => 0,
            ]);

            return response()->json([
                'message' => '打卡成功',
                'check_in' => $checkIn,
            ]);
        } finally {
            $this->lockService->release($lockKey, $token);
        }
    }

    /**
     * 获取打卡日历
     */
    public function getCalendar(int $year, int $month): JsonResponse
    {
        $user = Auth::user();

        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = now()->setYear($year)->setMonth($month)->endOfMonth();

        $calendar = $this->generateCalendar($user->id, $startDate, $endDate);

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

        $calendar = $this->generateCalendar($user->id, $startDate, $endDate);

        return response()->json([
            'year' => $year,
            'calendar' => $calendar,
        ]);
    }

    /**
     * 获取最近 365 天的打卡日历(包含今天)
     */
    public function getCalendarLast365(): JsonResponse
    {
        $user = Auth::user();

        $endDate = now()->endOfDay();
        $startDate = now()->startOfDay()->subDays(364); // 共 365 天

        $calendar = $this->generateCalendar($user->id, $startDate, $endDate);

        return response()->json([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'calendar' => $calendar,
        ]);
    }

    /**
     * Generate calendar data for a date range
     *
     * @return array<int, array{date: string, checked: bool, new_words_count: int, review_words_count: int}>
     */
    private function generateCalendar(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $checkIns = CheckIn::where('user_id', $userId)
            ->whereBetween('check_in_date', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->check_in_date)->toDateString());

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

        return $calendar;
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

        // 总单词数(当前学习的单词书)
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
            ->whereDate('check_in_date', now()->toDateString())
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
