<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RemoveBackgroundJob;
use App\Services\File\ImageProcessingService;
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

        $job->handle(app(RmbgStatusService::class), app(ImageProcessingService::class));

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

        $job->handle(app(RmbgStatusService::class), app(ImageProcessingService::class));

        $status = app(RmbgStatusService::class)->get($compressedPath);
        $this->assertSame('failed', $status['status']);
        $this->assertStringContainsString('HTTP 500', $status['message']);
    }
}
