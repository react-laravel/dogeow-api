<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\WebPushSummaryNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    private const SUMMARY_PUSH_COOLDOWN_MINUTES = 5;

    /**
     * 未读通知列表 + 数量；若存在未读且距上次汇总推送超过 N 分钟，则补发一条汇总 Web Push。
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->unreadNotifications();
        $count = $query->count();
        $notifications = $query->latest()->limit(50)->get();

        $items = $notifications->map(function ($n) {
            return [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'created_at' => $n->created_at->toIso8601String(),
            ];
        });
        $this->maybeSendSummaryPush($user, $count);

        return $this->success([
            'count' => $count,
            'items' => $items,
        ]);
    }

    /**
     * 有未读且冷却期内未发过汇总推送时，发一条汇总 Web Push。
     */
    private function maybeSendSummaryPush($user, int $unreadCount): void
    {
        if ($unreadCount <= 0) {
            return;
        }

        $cacheKey = "user:{$user->id}:unread_summary_push_at";
        $lastSent = Cache::get($cacheKey);
        if ($lastSent && now()->diffInMinutes($lastSent) < self::SUMMARY_PUSH_COOLDOWN_MINUTES) {
            return;
        }

        if ($user->pushSubscriptions()->count() === 0) {
            return;
        }

        $user->notify(new WebPushSummaryNotification($unreadCount, '/chat'));
        Cache::put($cacheKey, now(), now()->addMinutes(self::SUMMARY_PUSH_COOLDOWN_MINUTES + 1));
    }

    /**
     * 标记一条为已读。
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->unreadNotifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return $this->success([], '已标记为已读');
    }

    /**
     * 全部标记为已读。
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->each(fn ($n) => $n->markAsRead());

        return $this->success([], '已全部标记为已读');
    }
}
