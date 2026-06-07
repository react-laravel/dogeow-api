<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RemoveBackgroundJob;
use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Services\File\ImageProcessingService;
use App\Services\File\RmbgItemImageLinkerService;
use App\Services\File\RmbgStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemoveBackgroundJobTest extends TestCase
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
    }

    public function test_job_replaces_compressed_image_and_marks_done(): void
    {
        $userId = 7;
        $originPath = "uploads/{$userId}/abc-origin.jpg";
        $compressedPath = "uploads/{$userId}/abc.jpg";

        $image = UploadedFile::fake()->image('origin.jpg', 120, 120);
        Storage::disk('public')->put($originPath, file_get_contents($image->getRealPath()));
        Storage::disk('public')->put($compressedPath, file_get_contents($image->getRealPath()));

        Http::fake([
            'https://rmbg.example.com/api/remove-bg' => Http::response(
                file_get_contents($image->getRealPath()),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $this->mock(ImageProcessingService::class, function ($mock): void {
            $mock->shouldReceive('createThumbnailFromCompressed')
                ->once()
                ->andReturn(['success' => true]);
        });

        $job = new RemoveBackgroundJob(
            $userId,
            $compressedPath,
            $originPath,
            'https://example.com/storage/' . $originPath,
        );

        $job->handle(
            app(RmbgStatusService::class),
            app(ImageProcessingService::class),
            app(RmbgItemImageLinkerService::class),
        );

        $newPath = "uploads/{$userId}/abc.png";
        $this->assertTrue(Storage::disk('public')->exists($newPath));
        $this->assertFalse(Storage::disk('public')->exists($compressedPath));
        $this->assertTrue(Storage::disk('public')->exists($originPath));

        $status = app(RmbgStatusService::class)->get($compressedPath);
        $this->assertSame('done', $status['status']);
        $this->assertSame($newPath, $status['path']);
    }

    public function test_job_marks_failed_when_service_returns_error(): void
    {
        $compressedPath = 'uploads/1/abc.jpg';

        Http::fake([
            'https://rmbg.example.com/api/remove-bg' => Http::response(['error' => 'boom'], 500),
        ]);

        $job = new RemoveBackgroundJob(
            1,
            $compressedPath,
            'uploads/1/abc-origin.jpg',
            'https://example.com/storage/uploads/1/abc-origin.jpg',
        );

        $job->handle(
            app(RmbgStatusService::class),
            app(ImageProcessingService::class),
            app(RmbgItemImageLinkerService::class),
        );

        $status = app(RmbgStatusService::class)->get($compressedPath);
        $this->assertSame('failed', $status['status']);
        $this->assertStringContainsString('HTTP 500', $status['message']);
    }

    public function test_job_applies_to_linked_item_image(): void
    {
        $userId = 3;
        $item = Item::factory()->create();
        $compressedPath = "uploads/{$userId}/photo.jpg";
        $originPath = "uploads/{$userId}/photo-origin.jpg";
        $itemImagePath = "items/{$item->id}/photo.jpg";
        $itemOriginPath = "items/{$item->id}/photo-origin.jpg";

        $image = UploadedFile::fake()->image('photo.jpg', 120, 120);
        $pngBytes = file_get_contents($image->getRealPath());
        Storage::disk('public')->put($itemImagePath, $pngBytes);
        Storage::disk('public')->put($itemOriginPath, $pngBytes);

        $itemImage = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => $itemImagePath,
        ]);

        app(RmbgItemImageLinkerService::class)->link($compressedPath, $itemImage->id);

        Http::fake([
            'https://rmbg.example.com/api/remove-bg' => Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->mock(ImageProcessingService::class, function ($mock): void {
            $mock->shouldReceive('createThumbnailFromCompressed')
                ->once()
                ->andReturn(['success' => true]);
        });

        $job = new RemoveBackgroundJob(
            $userId,
            $compressedPath,
            $originPath,
            'https://example.com/storage/' . $originPath,
        );

        $job->handle(
            app(RmbgStatusService::class),
            app(ImageProcessingService::class),
            app(RmbgItemImageLinkerService::class),
        );

        $itemImage->refresh();
        $this->assertSame("items/{$item->id}/photo.png", $itemImage->path);
        $this->assertTrue(Storage::disk('public')->exists("items/{$item->id}/photo.png"));
        $this->assertFalse(Storage::disk('public')->exists($itemImagePath));

        $status = app(RmbgStatusService::class)->get($compressedPath);
        $this->assertSame('done', $status['status']);
    }

    public function test_job_applies_to_item_linked_during_rmbg_request(): void
    {
        $userId = 5;
        $item = Item::factory()->create();
        $compressedPath = "uploads/{$userId}/late.jpg";
        $originPath = "uploads/{$userId}/late-origin.jpg";
        $itemImagePath = "items/{$item->id}/late.jpg";
        $itemOriginPath = "items/{$item->id}/late-origin.jpg";

        $image = UploadedFile::fake()->image('late.jpg', 120, 120);
        $pngBytes = file_get_contents($image->getRealPath());
        Storage::disk('public')->put($itemImagePath, $pngBytes);
        Storage::disk('public')->put($itemOriginPath, $pngBytes);

        $itemImage = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => $itemImagePath,
        ]);

        $linker = app(RmbgItemImageLinkerService::class);

        Http::fake(function () use ($linker, $compressedPath, $itemImage, $pngBytes) {
            $linker->link($compressedPath, $itemImage->id);

            return Http::response($pngBytes, 200, ['Content-Type' => 'image/png']);
        });

        $this->mock(ImageProcessingService::class, function ($mock): void {
            $mock->shouldReceive('createThumbnailFromCompressed')
                ->once()
                ->andReturn(['success' => true]);
        });

        $job = new RemoveBackgroundJob(
            $userId,
            $compressedPath,
            $originPath,
            'https://example.com/storage/' . $originPath,
        );

        $job->handle(
            app(RmbgStatusService::class),
            app(ImageProcessingService::class),
            $linker,
        );

        $itemImage->refresh();
        $this->assertSame("items/{$item->id}/late.png", $itemImage->path);
        $this->assertFalse(Storage::disk('public')->exists("uploads/{$userId}/late.png"));
    }
}
