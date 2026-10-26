<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessUploadedImageJob;
use App\Jobs\RemoveBackgroundJob;
use App\Services\File\ImageProcessingService;
use App\Services\File\RmbgStatusService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessUploadedImageRetirementTest extends TestCase
{
    public function test_legacy_upload_job_still_compresses_but_never_dispatches_retired_background_removal(): void
    {
        config(['services.rmbg.url' => '', 'cache.default' => 'array']);
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('public');
        $origin = 'uploads/4/photo-origin.jpg';
        $compressed = 'uploads/4/photo.jpg';
        Storage::disk('public')->put($origin, 'original-image');
        Storage::disk('public')->put($compressed, 'compressed-image');
        $service = $this->mock(ImageProcessingService::class, function ($mock) use ($origin, $compressed): void {
            $mock->shouldReceive('processImage')->once()->with($origin, $compressed)->andReturn(['success' => true]);
        });

        $job = new ProcessUploadedImageJob($origin, $compressed, [
            'user_id' => 4,
            'remove_bg' => true,
            'compressed_relative_path' => $compressed,
            'origin_relative_path' => $origin,
            'origin_url' => 'https://example.com/original.jpg',
        ]);
        $job->handle($service);

        Queue::assertNotPushed(RemoveBackgroundJob::class);
        Http::assertNothingSent();
        $this->assertSame('original-image', Storage::disk('public')->get($origin));
        $this->assertSame('compressed-image', Storage::disk('public')->get($compressed));
        $this->assertSame('failed', app(RmbgStatusService::class)->get($compressed)['status']);
    }
}
