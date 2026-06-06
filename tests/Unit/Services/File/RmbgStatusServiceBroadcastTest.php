<?php

namespace Tests\Unit\Services\File;

use App\Events\Upload\RmbgStatusUpdated;
use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Services\File\RmbgItemImageLinkerService;
use App\Services\File\RmbgStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RmbgStatusServiceBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_set_done_dispatches_rmbg_status_updated_event(): void
    {
        Event::fake([RmbgStatusUpdated::class]);

        app(RmbgStatusService::class)->setDone('uploads/3/abc.jpg', [
            'path' => 'uploads/3/abc.png',
            'url' => 'https://example.com/abc.png',
        ]);

        Event::assertDispatched(RmbgStatusUpdated::class, function (RmbgStatusUpdated $event): bool {
            return $event->userId === 3
                && $event->uploadPath === 'uploads/3/abc.jpg'
                && $event->payload['status'] === 'done'
                && $event->payload['path'] === 'uploads/3/abc.png';
        });
    }

    public function test_broadcast_includes_linked_item_metadata(): void
    {
        Event::fake([RmbgStatusUpdated::class]);

        $item = Item::factory()->create();
        $itemImage = ItemImage::factory()->create(['item_id' => $item->id]);
        $uploadPath = 'uploads/9/photo.jpg';

        app(RmbgItemImageLinkerService::class)->link($uploadPath, $itemImage->id);
        app(RmbgStatusService::class)->setProcessing($uploadPath);

        Event::assertDispatched(RmbgStatusUpdated::class, function (RmbgStatusUpdated $event) use ($item, $itemImage, $uploadPath): bool {
            return $event->uploadPath === $uploadPath
                && $event->itemId === $item->id
                && $event->itemImageId === $itemImage->id
                && $event->payload['status'] === 'processing';
        });
    }
}
