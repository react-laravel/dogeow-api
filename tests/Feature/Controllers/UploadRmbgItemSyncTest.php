<?php

namespace Tests\Feature\Controllers;

use App\Jobs\ProcessUploadedImageJob;
use App\Jobs\RemoveBackgroundJob;
use App\Models\Thing\Item;
use App\Models\User;
use App\Services\File\ImageProcessingService;
use App\Services\File\RmbgItemImageLinkerService;
use App\Services\File\RmbgStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadRmbgItemSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Storage::fake('public');
        config([
            'services.rmbg.url' => 'https://rmbg.example.com/api/remove-bg',
            'services.rmbg.timeout' => 30,
        ]);
        $this->mock(ImageProcessingService::class, function ($mock): void {
            $mock->shouldReceive('processImage')->andReturn(['success' => true]);
            $mock->shouldReceive('createThumbnailFromCompressed')->andReturn(['success' => true]);
        });
    }

    public function test_create_item_before_rmbg_done_updates_item_image_when_job_finishes(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $uploadResponse = $this->post('/api/upload/images', [
            'images' => [UploadedFile::fake()->image('photo.jpg')],
            'remove_bg' => '1',
        ]);

        $uploadResponse->assertStatus(200);
        $uploadPath = $uploadResponse->json('0.path');
        $originPath = $uploadResponse->json('0.origin_path');

        Storage::disk('public')->put($uploadPath, 'fake compressed image');
        Storage::disk('public')->put($originPath, 'fake origin image');

        $compressedAbsPath = storage_path('app/public/' . $uploadPath);
        $originAbsPath = storage_path('app/public/' . $originPath);
        if (! is_dir(dirname($compressedAbsPath))) {
            mkdir(dirname($compressedAbsPath), 0755, true);
        }
        file_put_contents($compressedAbsPath, 'fake compressed image');
        file_put_contents($originAbsPath, 'fake origin image');

        Queue::assertPushed(ProcessUploadedImageJob::class);

        $itemResponse = $this->postJson('/api/things/items', [
            'name' => '测试物品',
            'image_paths' => [$uploadPath],
        ]);

        $itemResponse->assertStatus(201);
        $itemId = $itemResponse->json('data.id');
        $itemImageId = $itemResponse->json('data.images.0.id');
        $itemImagePath = $itemResponse->json('data.images.0.path');

        $this->assertStringStartsWith("items/{$itemId}/", $itemImagePath);
        $this->assertSame('pending', $itemResponse->json('data.images.0.rmbg_status'));

        $this->assertSame(
            $itemImageId,
            app(RmbgItemImageLinkerService::class)->getItemImageIdForUploadPath($uploadPath)
        );

        $pngBytes = str_repeat('x', 120);
        Http::fake([
            'https://rmbg.example.com/api/remove-bg' => Http::response($pngBytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $job = new RemoveBackgroundJob(
            $user->id,
            $uploadPath,
            $originPath,
            'https://example.com/storage/' . $originPath,
        );
        $job->handle(
            app(RmbgStatusService::class),
            app(ImageProcessingService::class),
            app(RmbgItemImageLinkerService::class),
        );

        $item = Item::query()->with('images')->findOrFail($itemId);
        $updatedImage = $item->images->first();

        $this->assertStringEndsWith('.png', $updatedImage->path);
        $this->assertTrue(Storage::disk('public')->exists($updatedImage->path));
        $this->assertNull($updatedImage->rmbg_status);
        $this->assertNull(app(RmbgItemImageLinkerService::class)->getItemImageIdForUploadPath($uploadPath));
    }
}
