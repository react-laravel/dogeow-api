<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * 通用 Web Push 通知，用于向用户发送标题、正文、跳转链接等。
 * 同时写入 database 通道，用于未读列表与「打开时补发汇总」。
 */
class WebPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $notificationId;

    public function __construct(
        public string $title,
        public string $body = '',
        public string $url = '/',
        public ?string $icon = null,
        public ?string $tag = null
    ) {
        $this->notificationId = Str::uuid()->toString();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [DatabaseChannel::class, WebPushChannel::class];
    }

    /**
     * Database 通道：供未读列表与前端展示。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon ?? '/480.png',
            'notification_id' => $this->notificationId,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $icon = $this->icon ?? '/480.png';
        // 把通知 ID 放进 data，点击通知时前端可用来标记已读
        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($icon)
            ->badge('/80.png')
            ->data([
                'url' => $this->url,
                'notification_id' => $this->notificationId,
            ])
            ->options(['TTL' => 86400]);

        if ($this->tag !== null) {
            $message->tag($this->tag);
        }

        return $message;
    }
}
