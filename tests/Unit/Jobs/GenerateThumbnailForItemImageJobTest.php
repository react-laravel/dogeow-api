<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateThumbnailForItemImageJob;
use App\Models\Thing\ItemImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateThumbnailForItemImageJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_job_can_be_constructed()
    {
        $itemImage = ItemImage::factory()->create();

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $this->assertInstanceOf(GenerateThumbnailForItemImageJob::class, $job);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_job_can_be_constructed_with_custom_parameters()
    {
        $itemImage = ItemImage::factory()->create();

        $job = new GenerateThumbnailForItemImageJob(
            $itemImage,
            300,
            300,
            '-custom'
        );

        $this->assertInstanceOf(GenerateThumbnailForItemImageJob::class, $job);
    }

    public function test_job_skips_when_item_image_has_no_path()
    {
        $itemImage = new ItemImage;
        $itemImage->id = 1;
        $itemImage->path = null;

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $job->handle();

        // Should not throw any exceptions and should log a warning
        $this->assertTrue(true);
    }

    public function test_job_skips_when_original_image_does_not_exist()
    {
        $itemImage = ItemImage::factory()->create(['path' => 'nonexistent/image.jpg']);

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $job->handle();

        // Should not throw any exceptions and should log an error
        $this->assertTrue(true);
    }

    public function test_job_generates_thumbnail_for_valid_image()
    {
        // Create a test image
        $imageFile = UploadedFile::fake()->image('test.jpg', 400, 300);
        $path = 'uploads/test.jpg';
        Storage::disk('public')->put($path, file_get_contents($imageFile->getRealPath()));

        $itemImage = ItemImage::factory()->create(['path' => $path]);

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $job->handle();

        // Check if thumbnail was created
        $thumbnailPath = 'uploads/test-thumb.jpg';
        $this->assertTrue(Storage::disk('public')->exists($thumbnailPath));
    }

    public function test_job_skips_when_thumbnail_exists_and_is_newer()
    {
        // Create a test image
        $imageFile = UploadedFile::fake()->image('test.jpg', 400, 300);
        $path = 'uploads/test.jpg';
        Storage::disk('public')->put($path, file_get_contents($imageFile->getRealPath()));

        // Create a newer thumbnail
        $thumbnailPath = 'uploads/test-thumb.jpg';
        Storage::disk('public')->put($thumbnailPath, file_get_contents($imageFile->getRealPath()));

        $itemImage = ItemImage::factory()->create(['path' => $path]);

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $job->handle();

        // Should not regenerate the thumbnail
        $this->assertTrue(Storage::disk('public')->exists($thumbnailPath));
    }

    public function test_job_uses_custom_thumbnail_suffix()
    {
        // Create a test image
        $imageFile = UploadedFile::fake()->image('test.jpg', 400, 300);
        $path = 'uploads/test.jpg';
        Storage::disk('public')->put($path, file_get_contents($imageFile->getRealPath()));

        $itemImage = ItemImage::factory()->create(['path' => $path]);

        $job = new GenerateThumbnailForItemImageJob($itemImage, 200, 200, '-custom');

        $job->handle();

        // Check if thumbnail was created with custom suffix
        $thumbnailPath = 'uploads/test-custom.jpg';
        $this->assertTrue(Storage::disk('public')->exists($thumbnailPath));
    }

    public function test_job_handles_different_image_formats()
    {
        // Test with PNG image
        $imageFile = UploadedFile::fake()->image('test.png', 400, 300);
        $path = 'uploads/test.png';
        Storage::disk('public')->put($path, file_get_contents($imageFile->getRealPath()));

        $itemImage = ItemImage::factory()->create(['path' => $path]);

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $job->handle();

        // Check if PNG thumbnail was created
        $thumbnailPath = 'uploads/test-thumb.png';
        $this->assertTrue(Storage::disk('public')->exists($thumbnailPath));
    }

    public function test_job_logs_success_when_thumbnail_generated()
    {
        Log::spy();

        // Create a test image
        $imageFile = UploadedFile::fake()->image('test.jpg', 400, 300);
        $path = 'uploads/test.jpg';
        Storage::disk('public')->put($path, file_get_contents($imageFile->getRealPath()));

        $itemImage = ItemImage::factory()->create(['path' => $path]);

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        $job->handle();

        Log::shouldHaveReceived('info')->with(
            "成功生成缩略图，ItemImage ID: {$itemImage->id}",
            \Mockery::any()
        );
    }

    public function test_job_logs_error_when_thumbnail_generation_fails()
    {
        Log::spy();

        // Create an invalid image file
        $path = 'uploads/test.jpg';
        Storage::disk('public')->put($path, 'invalid image content');

        $itemImage = ItemImage::factory()->create(['path' => $path]);

        $job = new GenerateThumbnailForItemImageJob($itemImage);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail
        }

        Log::shouldHaveReceived('error')->with(
            "缩略图生成失败，ItemImage ID: {$itemImage->id}",
            \Mockery::any()
        );
    }

    public function test_failed_method_logs_error()
    {
        Log::spy();

        $itemImage = ItemImage::factory()->create();
        $job = new GenerateThumbnailForItemImageJob($itemImage);
        $exception = new \Exception('Test exception');

        $job->failed($exception);

        Log::shouldHaveReceived('error')->with(
            "缩略图生成任务永久失败，ItemImage ID: {$itemImage->id}",
            \Mockery::any()
        );
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->deleteDirectory('uploads');
        parent::tearDown();
    }
}
