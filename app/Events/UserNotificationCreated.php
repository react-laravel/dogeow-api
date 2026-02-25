<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 用户收到新的数据库通知时，实时推送给前端刷新未读通知。
 */
class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $notificationId,
        public string $type,
        public array $data,
        public string $createdAt,
        public int $unreadCount
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}.notifications"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notificationId,
                'type' => $this->type,
                'data' => $this->data,
                'created_at' => $this->createdAt,
            ],
            'count' => $this->unreadCount,
        ];
    }
}
