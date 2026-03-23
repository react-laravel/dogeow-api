<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 日志更新事件，当 Laravel 日志文件有新内容时触发。
 */
class LogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $date,
        public int $size
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('log-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'log.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'date' => $this->date,
            'size' => $this->size,
        ];
    }
}
