<?php

namespace Tests\Unit\Events\Upload;

use App\Events\Upload\RmbgStatusUpdated;
use Tests\TestCase;

class RmbgStatusUpdatedTest extends TestCase
{
    public function test_broadcast_on_returns_user_uploads_channel(): void
    {
        $event = new RmbgStatusUpdated(
            userId: 5,
            uploadPath: 'uploads/5/abc.jpg',
            payload: ['status' => 'done', 'path' => 'uploads/5/abc.png'],
            itemId: 10,
            itemImageId: 20,
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('user.5.uploads', $channels[0]->name);
    }

    public function test_broadcast_as_and_with_payload(): void
    {
        $event = new RmbgStatusUpdated(
            userId: 1,
            uploadPath: 'uploads/1/abc.jpg',
            payload: [
                'status' => 'failed',
                'message' => 'boom',
            ],
        );

        $this->assertSame('rmbg.status.updated', $event->broadcastAs());
        $this->assertSame([
            'upload_path' => 'uploads/1/abc.jpg',
            'status' => 'failed',
            'path' => null,
            'url' => null,
            'thumbnail_url' => null,
            'thumbnail_path' => null,
            'origin_path' => null,
            'origin_url' => null,
            'message' => 'boom',
            'item_id' => null,
            'item_image_id' => null,
        ], $event->broadcastWith());
    }
}
