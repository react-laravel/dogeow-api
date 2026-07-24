<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\Ai\VisionUploadController;
use App\Services\UpyunService;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionClass;
use Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class VisionUploadControllerUnitTest extends TestCase
{
    public function test_constructor_initializes_upyun_service(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $controller = new VisionUploadController($upyunService);

        $reflection = new ReflectionClass($controller);
        $property = $reflection->getProperty('upyunService');
        $property->setAccessible(true);

        $this->assertSame($upyunService, $property->getValue($controller));
    }

    public function test_upload_validation_does_not_use_laravel_image_rule_so_heic_can_reach_controller(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(false);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')
            ->once()
            ->with(
                Mockery::on(function (array $rules): bool {
                    $imageRules = $rules['image'] ?? [];
                    $imageRules = is_array($imageRules) ? $imageRules : explode('|', $imageRules);

                    return in_array('file', $imageRules, true)
                        && ! in_array('image', $imageRules, true);
                }),
                Mockery::on(fn ($messages): bool => is_array($messages))
            );
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_upload_returns_400_when_uploaded_file_is_invalid(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(false);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => '上传的图片无效',
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_returns_default_message_when_upyun_fails_without_message(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->willReturn([
                'success' => false,
            ]);

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/jpeg');
        $file->shouldReceive('move')->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => '上传失败',
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_returns_success_and_uses_png_extension_for_png_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->callback(fn ($v) => is_string($v)),
                $this->callback(fn ($remotePath) => is_string($remotePath) && str_ends_with($remotePath, '.png')),
                'image/png'
            )
            ->willReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.png',
            ]);

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/png');
        $file->shouldReceive('move')->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => [
                'url' => 'https://example.com/vision/test.png',
            ],
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_uses_heic_extension_for_heic_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->callback(fn ($v) => is_string($v)),
                $this->callback(fn ($remotePath) => is_string($remotePath) && str_ends_with($remotePath, '.heic')),
                'image/heic'
            )
            ->willReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.heic',
            ]);

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/heic');
        $file->shouldReceive('move')->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => [
                'url' => 'https://example.com/vision/test.heic',
            ],
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_uses_webp_extension_for_webp_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->callback(fn ($v) => is_string($v)),
                $this->callback(fn ($remotePath) => is_string($remotePath) && str_ends_with($remotePath, '.webp')),
                'image/webp'
            )
            ->willReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.webp',
            ]);

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/webp');
        $file->shouldReceive('move')->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => [
                'url' => 'https://example.com/vision/test.webp',
            ],
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_uses_gif_extension_for_gif_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->callback(fn ($v) => is_string($v)),
                $this->callback(fn ($remotePath) => is_string($remotePath) && str_ends_with($remotePath, '.gif')),
                'image/gif'
            )
            ->willReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.gif',
            ]);

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/gif');
        $file->shouldReceive('move')->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'data' => [
                'url' => 'https://example.com/vision/test.gif',
            ],
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_returns_failure_message_from_upyun_when_provided(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->willReturn([
                'success' => false,
                'message' => '云存储异常',
            ]);

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/jpeg');
        $file->shouldReceive('move')->once();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => '云存储异常',
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_deletes_temp_file_in_finally_after_upload(): void
    {
        $capturedTempPath = null;

        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->willReturnCallback(function ($tempPath, $remotePath, $mime) use (&$capturedTempPath) {
                $capturedTempPath = $tempPath;
                $this->assertFileExists($tempPath);
                $this->assertSame('image/jpeg', $mime);

                return [
                    'success' => true,
                    'url' => 'https://example.com/vision/finally.jpg',
                ];
            });

        $controller = new VisionUploadController($upyunService);

        $file = Mockery::mock();
        $file->shouldReceive('isValid')->once()->andReturn(true);
        $file->shouldReceive('getMimeType')->once()->andReturn('image/jpeg');
        $file->shouldReceive('move')->once()->andReturnUsing(function ($dir, $filename) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/' . $filename, 'temp-image-content');
        });

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once();
        $request->shouldReceive('file')->once()->with('image')->andReturn($file);

        $response = $controller->upload($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedTempPath);
        $this->assertFileDoesNotExist($capturedTempPath);
    }

    public function test_validate_image_magic_bytes_accepts_valid_png(): void
    {
        $controller = new VisionUploadController($this->createMock(UpyunService::class));
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('validateImageMagicBytes');
        $method->setAccessible(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmpPath, "\x89PNG\r\n\x1a\n" . str_repeat('a', 100));

        try {
            $this->assertTrue($method->invoke($controller, $tmpPath));
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_validate_image_magic_bytes_accepts_valid_jpeg(): void
    {
        $controller = new VisionUploadController($this->createMock(UpyunService::class));
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('validateImageMagicBytes');
        $method->setAccessible(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'jpg');
        file_put_contents($tmpPath, "\xff\xd8\xff\xe0" . str_repeat('a', 100));

        try {
            $this->assertTrue($method->invoke($controller, $tmpPath));
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_validate_image_magic_bytes_accepts_valid_gif(): void
    {
        $controller = new VisionUploadController($this->createMock(UpyunService::class));
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('validateImageMagicBytes');
        $method->setAccessible(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'gif');
        file_put_contents($tmpPath, 'GIF89a' . str_repeat('a', 100));

        try {
            $this->assertTrue($method->invoke($controller, $tmpPath));
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_validate_image_magic_bytes_accepts_valid_webp(): void
    {
        $controller = new VisionUploadController($this->createMock(UpyunService::class));
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('validateImageMagicBytes');
        $method->setAccessible(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'webp');
        file_put_contents($tmpPath, 'RIFFxxxxWEBP' . str_repeat('a', 100));

        try {
            $this->assertTrue($method->invoke($controller, $tmpPath));
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_validate_image_magic_bytes_rejects_non_image(): void
    {
        $controller = new VisionUploadController($this->createMock(UpyunService::class));
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('validateImageMagicBytes');
        $method->setAccessible(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'fake');
        file_put_contents($tmpPath, '<html><body>PHP shell</body></html>' . str_repeat('a', 100));

        try {
            $this->assertFalse($method->invoke($controller, $tmpPath));
        } finally {
            @unlink($tmpPath);
        }
    }

    public function test_validate_image_magic_bytes_rejects_executable(): void
    {
        $controller = new VisionUploadController($this->createMock(UpyunService::class));
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('validateImageMagicBytes');
        $method->setAccessible(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($tmpPath, "MZ\x90\x00" . str_repeat('a', 100)); // PE executable header

        try {
            $this->assertFalse($method->invoke($controller, $tmpPath));
        } finally {
            @unlink($tmpPath);
        }
    }
}
