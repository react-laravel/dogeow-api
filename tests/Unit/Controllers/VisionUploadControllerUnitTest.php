<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\VisionUploadController;
use App\Services\UpyunService;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

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
                $this->isType('string'),
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
            'url' => 'https://example.com/vision/test.png',
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_uses_default_jpg_extension_for_unknown_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->isType('string'),
                $this->callback(fn ($remotePath) => is_string($remotePath) && str_ends_with($remotePath, '.jpg')),
                'image/heic'
            )
            ->willReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.jpg',
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
            'url' => 'https://example.com/vision/test.jpg',
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_uses_webp_extension_for_webp_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->isType('string'),
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
            'url' => 'https://example.com/vision/test.webp',
        ], json_decode($response->getContent(), true));
    }

    public function test_upload_uses_gif_extension_for_gif_mime(): void
    {
        $upyunService = $this->createMock(UpyunService::class);
        $upyunService->expects($this->once())
            ->method('upload')
            ->with(
                $this->isType('string'),
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
            'url' => 'https://example.com/vision/test.gif',
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
}
