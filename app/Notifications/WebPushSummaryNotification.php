<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * 仅 Web Push 的汇总通知（不写 database），用于「打开浏览器时补发一条」。
 */
class WebPushSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $unreadCount,
        public string $url = '/chat'
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $n = $this->unreadCount;
        $title = $n === 1 ? '你有 1 条未读消息' : "你有 {$n} 条未读消息";

        return (new WebPushMessage)
            ->title($title)
            ->body('点击查看')
            ->icon('/480.png')
            ->badge('/80.png')
            ->data(['url' => $this->url])
            ->options(['TTL' => 300]);
    }
}
