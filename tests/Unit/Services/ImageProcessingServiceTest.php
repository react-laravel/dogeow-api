<?php

namespace Tests\Unit\Services;

use App\Services\File\ImageProcessingService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ImageProcessingServiceTest extends TestCase
{
    protected ImageProcessingService $imageProcessingService;

    protected string $testImagePath;

    protected string $testCompressedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageProcessingService = new ImageProcessingService;

        // Create test image directory
        $testDir = storage_path('app/public/test');
        if (! file_exists($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $this->testImagePath = $testDir . '/test-origin.jpg';
        $this->testCompressedPath = $testDir . '/test-compressed.jpg';

        // Create a simple test image (1x1 pixel JPEG)
        $this->createTestImage();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testDir = dirname($this->testImagePath);
        if (file_exists($testDir) && is_dir($testDir)) {
            foreach (glob($testDir . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Remove test directory
        if (file_exists($testDir) && is_dir($testDir)) {
            rmdir($testDir);
        }

        parent::tearDown();
    }

    private function createTestImage(): void
    {
        // Create a simple 1x1 pixel JPEG image
        $image = imagecreate(1, 1);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $this->testImagePath);
        imagedestroy($image);
    }

    public function test_process_image_successfully()
    {
        $result = $this->imageProcessingService->processImage($this->testImagePath, $this->testCompressedPath);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertEquals(1, $result['width']);
        $this->assertEquals(1, $result['height']);

        // Check that compressed and thumbnail files were created
        $this->assertFileExists($this->testCompressedPath);
        $this->assertFileExists(str_replace('-origin.', '-thumb.', $this->testImagePath));
    }

    public function test_process_image_with_nonexistent_file()
    {
        $nonexistentPath = '/nonexistent/path/image.jpg';

        $result = $this->imageProcessingService->processImage($nonexistentPath, $this->testCompressedPath);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
    }

    public function test_process_image_logs_error_on_failure()
    {
        Log::spy();

        $nonexistentPath = '/nonexistent/path/image.jpg';

        $this->imageProcessingService->processImage($nonexistentPath, $this->testCompressedPath);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_create_thumbnail_for_small_image()
    {
        // Create a small image (100x100)
        $smallImagePath = storage_path('app/public/test/small-origin.jpg');
        $image = imagecreate(100, 100);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $smallImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($smallImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);

        // Check thumbnail was created
        $thumbnailPath = str_replace('-origin.', '-thumb.', $smallImagePath);
        $this->assertFileExists($thumbnailPath);

        // Clean up
        unlink($smallImagePath);
        unlink($thumbnailPath);
    }

    public function test_create_thumbnail_for_large_image()
    {
        // Create a large image (500x300)
        $largeImagePath = storage_path('app/public/test/large-origin.jpg');
        $image = imagecreate(500, 300);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $largeImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($largeImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);

        // Check thumbnail was created and scaled
        $thumbnailPath = str_replace('-origin.', '-thumb.', $largeImagePath);
        $this->assertFileExists($thumbnailPath);

        // Clean up
        unlink($largeImagePath);
        unlink($thumbnailPath);
    }

    public function test_create_compressed_image_for_large_image()
    {
        // Create a large image (1000x800)
        $largeImagePath = storage_path('app/public/test/very-large-origin.jpg');
        $image = imagecreate(1000, 800);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $largeImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($largeImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);

        // Check compressed image was created
        $this->assertFileExists($this->testCompressedPath);

        // Clean up
        unlink($largeImagePath);
    }

    public function test_process_image_with_invalid_image_file()
    {
        // Create an invalid image file (just text)
        $invalidImagePath = storage_path('app/public/test/invalid-origin.jpg');
        file_put_contents($invalidImagePath, 'This is not an image');

        $result = $this->imageProcessingService->processImage($invalidImagePath, $this->testCompressedPath);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);

        // Clean up
        unlink($invalidImagePath);
    }

    public function test_process_image_with_different_image_formats()
    {
        // Test with PNG format
        $pngImagePath = storage_path('app/public/test/test-origin.png');
        $image = imagecreate(100, 100);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagepng($image, $pngImagePath);
        imagedestroy($image);

        $pngCompressedPath = storage_path('app/public/test/test-compressed.png');

        $result = $this->imageProcessingService->processImage($pngImagePath, $pngCompressedPath);

        $this->assertTrue($result['success']);
        $this->assertFileExists($pngCompressedPath);

        // Clean up
        unlink($pngImagePath);
        unlink($pngCompressedPath);
        unlink(str_replace('-origin.', '-thumb.', $pngImagePath));
    }

    public function test_get_image_info_returns_dimensions_size_and_mime_type()
    {
        $result = $this->imageProcessingService->getImageInfo($this->testImagePath);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['width']);
        $this->assertSame(1, $result['height']);
        $this->assertGreaterThan(0, $result['size']);
        $this->assertSame('image/jpeg', $result['mime_type']);
    }

    public function test_get_image_info_returns_error_when_file_is_missing()
    {
        $result = $this->imageProcessingService->getImageInfo(storage_path('app/public/test/missing.jpg'));

        $this->assertFalse($result['success']);
        $this->assertSame('Image file not found', $result['message']);
    }

    public function test_process_image_with_wide_image()
    {
        // Create a wide image (800x200)
        $wideImagePath = storage_path('app/public/test/wide-origin.jpg');
        $image = imagecreate(800, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $wideImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($wideImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);
        $this->assertFileExists($this->testCompressedPath);

        // Clean up
        unlink($wideImagePath);
        unlink(str_replace('-origin.', '-thumb.', $wideImagePath));
    }

    public function test_process_image_with_tall_image()
    {
        // Create a tall image (200x800)
        $tallImagePath = storage_path('app/public/test/tall-origin.jpg');
        $image = imagecreate(200, 800);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $tallImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($tallImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);
        $this->assertFileExists($this->testCompressedPath);

        // Clean up
        unlink($tallImagePath);
        unlink(str_replace('-origin.', '-thumb.', $tallImagePath));
    }

    public function test_get_image_info_with_different_formats()
    {
        // Create a PNG image
        $pngImagePath = storage_path('app/public/test/test-info.png');
        $image = imagecreate(50, 50);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagepng($image, $pngImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->getImageInfo($pngImagePath);

        $this->assertTrue($result['success']);
        $this->assertSame('image/png', $result['mime_type']);
        $this->assertSame(50, $result['width']);
        $this->assertSame(50, $result['height']);

        // Clean up
        unlink($pngImagePath);
    }

    public function test_process_image_maintains_aspect_ratio()
    {
        // Create an image with specific aspect ratio
        $aspectImagePath = storage_path('app/public/test/aspect-origin.jpg');
        $image = imagecreate(400, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $aspectImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($aspectImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);
        $this->assertSame(400, $result['width']);
        $this->assertSame(200, $result['height']);

        // Clean up
        unlink($aspectImagePath);
        unlink(str_replace('-origin.', '-thumb.', $aspectImagePath));
    }

    public function test_process_image_skip_resize_for_tiny_image()
    {
        // Create a very small image (10x10)
        $tinyImagePath = storage_path('app/public/test/tiny-origin.jpg');
        $image = imagecreate(10, 10);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagejpeg($image, $tinyImagePath);
        imagedestroy($image);

        $result = $this->imageProcessingService->processImage($tinyImagePath, $this->testCompressedPath);

        $this->assertTrue($result['success']);
        $thumbnailPath = str_replace('-origin.', '-thumb.', $tinyImagePath);
        $this->assertFileExists($thumbnailPath);

        // Clean up
        unlink($tinyImagePath);
        unlink($thumbnailPath);
    }

    public function test_process_image_returns_thumbnail_error_when_thumbnail_creation_fails()
    {
        $readOnlyDir = storage_path('app/public/test/readonly-thumbnail');
        if (! file_exists($readOnlyDir)) {
            mkdir($readOnlyDir, 0755, true);
        }

        $originPath = $readOnlyDir . '/thumb-fail-origin.jpg';
        $image = imagecreate(100, 100);
        imagejpeg($image, $originPath);
        imagedestroy($image);

        chmod($readOnlyDir, 0555);

        try {
            $result = $this->imageProcessingService->processImage($originPath, $this->testCompressedPath);
            $this->assertFalse($result['success']);
            $this->assertSame('Failed to create thumbnail', $result['message']);
        } finally {
            chmod($readOnlyDir, 0755);
            if (file_exists($originPath)) {
                unlink($originPath);
            }
            $thumbnailPath = str_replace('-origin.', '-thumb.', $originPath);
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            if (file_exists($readOnlyDir)) {
                rmdir($readOnlyDir);
            }
        }
    }

    public function test_process_image_returns_compressed_error_when_compress_creation_fails()
    {
        $originPath = storage_path('app/public/test/compress-fail-origin.jpg');
        $originImage = imagecreate(100, 100);
        imagejpeg($originImage, $originPath);
        imagedestroy($originImage);

        $readOnlyDir = storage_path('app/public/test/readonly-compressed');
        if (! file_exists($readOnlyDir)) {
            mkdir($readOnlyDir, 0755, true);
        }
        $compressedPath = $readOnlyDir . '/compress-fail.jpg';

        chmod($readOnlyDir, 0555);

        try {
            $result = $this->imageProcessingService->processImage($originPath, $compressedPath);
            $this->assertFalse($result['success']);
            $this->assertSame('Failed to create compressed image', $result['message']);
        } finally {
            chmod($readOnlyDir, 0755);
            if (file_exists($originPath)) {
                unlink($originPath);
            }
            $thumbnailPath = str_replace('-origin.', '-thumb.', $originPath);
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            if (file_exists($compressedPath)) {
                unlink($compressedPath);
            }
            if (file_exists($readOnlyDir)) {
                rmdir($readOnlyDir);
            }
        }
    }

    public function test_get_image_info_returns_error_when_reader_throws_exception()
    {
        $directoryPath = storage_path('app/public/test');

        $result = $this->imageProcessingService->getImageInfo($directoryPath);

        $this->assertFalse($result['success']);
        $this->assertSame('Failed to get image info', $result['message']);
    }
}
