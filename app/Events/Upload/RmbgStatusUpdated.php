<?php

namespace App\Events\Upload;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 用户上传图片的去背景状态变更时，实时推送给前端。
 */
class RmbgStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $userId,
        public string $uploadPath,
        public array $payload,
        public ?int $itemId = null,
        public ?int $itemImageId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}.uploads"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rmbg.status.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'upload_path' => $this->uploadPath,
            'status' => $this->payload['status'] ?? 'unknown',
            'path' => $this->payload['path'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'thumbnail_url' => $this->payload['thumbnail_url'] ?? null,
            'thumbnail_path' => $this->payload['thumbnail_path'] ?? null,
            'origin_path' => $this->payload['origin_path'] ?? null,
            'origin_url' => $this->payload['origin_url'] ?? null,
            'message' => $this->payload['message'] ?? null,
            'item_id' => $this->itemId,
            'item_image_id' => $this->itemImageId,
        ];
    }
}
