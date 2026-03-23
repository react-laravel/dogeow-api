<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\UploadController;
use App\Http\Requests\UploadBatchImagesRequest;
use App\Services\File\FileStorageService;
use App\Services\File\ImageProcessingService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class UploadControllerUnitTest extends TestCase
{
    public function test_constructor_initializes_dependencies_and_error_messages(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $controller = new UploadController($storageService, $imageService);
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getUploadErrorMessage');
        $method->setAccessible(true);

        $this->assertSame(
            '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
            $method->invoke($controller, UPLOAD_ERR_INI_SIZE)
        );
        $this->assertSame(
            '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
            $method->invoke($controller, UPLOAD_ERR_FORM_SIZE)
        );
        $this->assertSame('文件只有部分被上传', $method->invoke($controller, UPLOAD_ERR_PARTIAL));
        $this->assertSame('没有文件被上传', $method->invoke($controller, UPLOAD_ERR_NO_FILE));
        $this->assertSame('找不到临时文件夹', $method->invoke($controller, UPLOAD_ERR_NO_TMP_DIR));
        $this->assertSame('文件写入失败', $method->invoke($controller, UPLOAD_ERR_CANT_WRITE));
        $this->assertSame('文件上传因扩展程序而停止', $method->invoke($controller, UPLOAD_ERR_EXTENSION));
        $this->assertSame('未知上传错误', $method->invoke($controller, 9999));
    }

    public function test_upload_batch_images_returns_500_when_directory_creation_fails(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => false,
            'message' => '创建目录失败',
        ]);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldNotReceive('hasFile');

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '创建目录失败'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_returns_default_message_when_directory_creation_fails_without_message(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => false,
        ]);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldNotReceive('hasFile');

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '创建用户目录失败'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_returns_400_when_no_images_found(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(false);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['message' => '没有找到上传的图片文件'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_returns_500_when_all_images_fail(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $invalidImage = Mockery::mock();
        $invalidImage->shouldReceive('isValid')->once()->andReturn(false);
        $invalidImage->shouldReceive('getError')->times(2)->andReturn(UPLOAD_ERR_PARTIAL);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$invalidImage]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '所有图片上传失败'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_returns_uploaded_items_when_some_images_succeed(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storageService->expects($this->once())->method('storeFile')->willReturnCallback(function ($file, $dirPath) {
            return [
                'success' => true,
                'origin_path' => 'uploads/0/origin.jpg',
                'compressed_path' => 'uploads/0/compressed.jpg',
                'origin_filename' => 'origin.jpg',
                'compressed_filename' => 'compressed.jpg',
            ];
        });

        $storageService->expects($this->once())->method('getPublicUrls')->willReturn([
            'origin_url' => 'https://example.com/origin.jpg',
            'compressed_url' => 'https://example.com/compressed.jpg',
        ]);

        $invalidImage = Mockery::mock();
        $invalidImage->shouldReceive('isValid')->once()->andReturn(false);
        $invalidImage->shouldReceive('getError')->times(2)->andReturn(UPLOAD_ERR_PARTIAL);

        $validImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $validImage->shouldReceive('isValid')->once()->andReturn(true);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$invalidImage, $validImage]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(1, $payload);
        $this->assertSame('uploads/0/compressed.jpg', $payload[0]['path']);
        $this->assertSame('uploads/0/origin.jpg', $payload[0]['origin_path']);
    }

    public function test_upload_batch_images_returns_500_when_outer_exception_is_thrown(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willThrowException(new \Exception('outer boom'));

        $request = Mockery::mock(UploadBatchImagesRequest::class);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '图片上传失败: outer boom'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_returns_500_when_store_file_fails_for_all_images(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storageService->expects($this->once())->method('storeFile')->willReturn([
            'success' => false,
            'message' => '存储失败',
        ]);

        $validImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $validImage->shouldReceive('isValid')->once()->andReturn(true);
        $validImage->shouldReceive('getClientOriginalName')->once()->andReturn('failed.jpg');

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$validImage]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '所有图片上传失败'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_returns_success_items_when_store_file_partially_fails(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storeCall = 0;
        $storageService->expects($this->exactly(2))->method('storeFile')->willReturnCallback(function () use (&$storeCall) {
            $storeCall++;
            if ($storeCall === 1) {
                return [
                    'success' => false,
                    'message' => '首张失败',
                ];
            }

            return [
                'success' => true,
                'origin_path' => 'uploads/0/origin-2.jpg',
                'compressed_path' => 'uploads/0/compressed-2.jpg',
                'origin_filename' => 'origin-2.jpg',
                'compressed_filename' => 'compressed-2.jpg',
            ];
        });

        $storageService->expects($this->once())->method('getPublicUrls')->willReturn([
            'origin_url' => 'https://example.com/origin-2.jpg',
            'compressed_url' => 'https://example.com/compressed-2.jpg',
        ]);

        $firstImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $firstImage->shouldReceive('isValid')->once()->andReturn(true);
        $firstImage->shouldReceive('getClientOriginalName')->once()->andReturn('first.jpg');

        $secondImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $secondImage->shouldReceive('isValid')->once()->andReturn(true);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$firstImage, $secondImage]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(1, $payload);
        $this->assertSame('uploads/0/compressed-2.jpg', $payload[0]['path']);
        $this->assertSame('uploads/0/origin-2.jpg', $payload[0]['origin_path']);
    }

    public function test_upload_batch_images_returns_empty_array_when_images_list_is_empty(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function test_upload_batch_images_continues_when_store_file_throws_for_one_image(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storeCall = 0;
        $storageService->expects($this->exactly(2))->method('storeFile')->willReturnCallback(function () use (&$storeCall) {
            $storeCall++;
            if ($storeCall === 1) {
                throw new \Exception('store boom');
            }

            return [
                'success' => true,
                'origin_path' => 'uploads/0/origin-ok.jpg',
                'compressed_path' => 'uploads/0/compressed-ok.jpg',
                'origin_filename' => 'origin-ok.jpg',
                'compressed_filename' => 'compressed-ok.jpg',
            ];
        });

        $storageService->expects($this->once())->method('getPublicUrls')->willReturn([
            'origin_url' => 'https://example.com/origin-ok.jpg',
            'compressed_url' => 'https://example.com/compressed-ok.jpg',
        ]);

        $firstImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $firstImage->shouldReceive('isValid')->once()->andReturn(true);
        $firstImage->shouldReceive('getClientOriginalName')->once()->andReturn('boom.jpg');

        $secondImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $secondImage->shouldReceive('isValid')->once()->andReturn(true);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$firstImage, $secondImage]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(1, $payload);
        $this->assertSame('uploads/0/compressed-ok.jpg', $payload[0]['path']);
        $this->assertSame('uploads/0/origin-ok.jpg', $payload[0]['origin_path']);
    }

    public function test_upload_batch_images_returns_500_when_store_file_throws_for_all_images(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storageService->expects($this->exactly(2))->method('storeFile')
            ->willThrowException(new \Exception('store crash'));

        $firstImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $firstImage->shouldReceive('isValid')->once()->andReturn(true);
        $firstImage->shouldReceive('getClientOriginalName')->once()->andReturn('a.jpg');

        $secondImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $secondImage->shouldReceive('isValid')->once()->andReturn(true);
        $secondImage->shouldReceive('getClientOriginalName')->once()->andReturn('b.jpg');

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$firstImage, $secondImage]);

        $controller = new UploadController($storageService, $imageService);
        $response = $controller->uploadBatchImages($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '所有图片上传失败'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_uses_image_processing_branch_and_returns_500_when_processing_fails(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storageService->expects($this->once())->method('storeFile')->willReturn([
            'success' => true,
            'origin_path' => 'uploads/0/origin-fail.jpg',
            'compressed_path' => 'uploads/0/compressed-fail.jpg',
            'origin_filename' => 'origin-fail.jpg',
            'compressed_filename' => 'compressed-fail.jpg',
        ]);

        $imageService->expects($this->once())->method('processImage')->with(
            'uploads/0/origin-fail.jpg',
            'uploads/0/compressed-fail.jpg'
        )->willReturn([
            'success' => false,
            'message' => 'process failed',
        ]);

        $validImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $validImage->shouldReceive('isValid')->once()->andReturn(true);
        $validImage->shouldReceive('getClientOriginalName')->once()->andReturn('fail.jpg');

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$validImage]);

        $controller = new UploadController($storageService, $imageService);

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $response = $controller->uploadBatchImages($request);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            ['message' => '所有图片上传失败'],
            json_decode($response->getContent(), true)
        );
    }

    public function test_upload_batch_images_uses_image_processing_branch_and_returns_processed_item(): void
    {
        $storageService = $this->createMock(FileStorageService::class);
        $imageService = $this->createMock(ImageProcessingService::class);

        $storageService->method('createUserDirectory')->willReturn([
            'success' => true,
            'directory_path' => 'uploads/0',
        ]);

        $storageService->expects($this->once())->method('storeFile')->willReturn([
            'success' => true,
            'origin_path' => 'uploads/0/origin-ok.jpg',
            'compressed_path' => 'uploads/0/compressed-ok.jpg',
            'origin_filename' => 'origin-ok.jpg',
            'compressed_filename' => 'compressed-ok.jpg',
        ]);

        $imageService->expects($this->once())->method('processImage')->with(
            'uploads/0/origin-ok.jpg',
            'uploads/0/compressed-ok.jpg'
        )->willReturn([
            'success' => true,
        ]);

        $storageService->expects($this->once())->method('getPublicUrls')->willReturn([
            'origin_url' => 'https://example.com/origin-ok.jpg',
            'compressed_url' => 'https://example.com/compressed-ok.jpg',
        ]);

        $validImage = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $validImage->shouldReceive('isValid')->once()->andReturn(true);

        $request = Mockery::mock(UploadBatchImagesRequest::class);
        $request->shouldReceive('hasFile')->once()->with('images')->andReturn(true);
        $request->shouldReceive('file')->once()->with('images')->andReturn([$validImage]);

        $controller = new UploadController($storageService, $imageService);

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $response = $controller->uploadBatchImages($request);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertCount(1, $payload);
        $this->assertSame('uploads/0/compressed-ok.jpg', $payload[0]['path']);
        $this->assertSame('uploads/0/origin-ok.jpg', $payload[0]['origin_path']);
    }
}
