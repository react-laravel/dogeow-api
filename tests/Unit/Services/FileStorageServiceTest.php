<?php

namespace Tests\Unit\Services;

use App\Services\File\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageServiceTest extends TestCase
{
    protected FileStorageService $fileStorageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileStorageService = new FileStorageService;
        Storage::fake('public');
    }

    public function test_store_file_successfully()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('basename', $result);
        $this->assertArrayHasKey('extension', $result);
        $this->assertArrayHasKey('compressed_filename', $result);
        $this->assertArrayHasKey('thumbnail_filename', $result);
        $this->assertArrayHasKey('origin_filename', $result);
        $this->assertArrayHasKey('compressed_path', $result);
        $this->assertArrayHasKey('origin_path', $result);

        $this->assertEquals('jpg', $result['extension']);
        $this->assertStringEndsWith('.jpg', $result['compressed_filename']);
        $this->assertStringEndsWith('-thumb.jpg', $result['thumbnail_filename']);
        $this->assertStringEndsWith('-origin.jpg', $result['origin_filename']);
    }

    public function test_store_file_without_extension()
    {
        $file = UploadedFile::fake()->create('test', 100);
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertTrue($result['success']);
        $this->assertEquals('jpg', $result['extension']); // Default extension
        $this->assertStringEndsWith('.jpg', $result['compressed_filename']);
    }

    public function test_store_file_with_different_extension()
    {
        $file = UploadedFile::fake()->image('test.png');
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertTrue($result['success']);
        $this->assertEquals('png', $result['extension']);
        $this->assertStringEndsWith('.png', $result['compressed_filename']);
        $this->assertStringEndsWith('-thumb.png', $result['thumbnail_filename']);
        $this->assertStringEndsWith('-origin.png', $result['origin_filename']);
    }

    public function test_create_user_directory()
    {
        $userId = 123;
        $expectedPath = storage_path('app/public/uploads/' . $userId);

        $result = $this->fileStorageService->createUserDirectory($userId);

        $this->assertTrue($result['success']);
        $this->assertEquals($expectedPath, $result['directory_path']);
        $this->assertDirectoryExists($result['directory_path']);
    }

    public function test_create_user_directory_when_already_exists()
    {
        $userId = 123;
        $expectedPath = storage_path('app/public/uploads/' . $userId);

        // Create directory first
        if (! file_exists($expectedPath)) {
            mkdir($expectedPath, 0755, true);
        }

        $result = $this->fileStorageService->createUserDirectory($userId);

        $this->assertTrue($result['success']);
        $this->assertEquals($expectedPath, $result['directory_path']);
        $this->assertDirectoryExists($result['directory_path']);
    }

    public function test_get_public_urls()
    {
        $userId = '123';
        $filenames = [
            'compressed_filename' => 'abc123.jpg',
            'thumbnail_filename' => 'abc123-thumb.jpg',
            'origin_filename' => 'abc123-origin.jpg',
        ];

        $result = $this->fileStorageService->getPublicUrls($userId, $filenames);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('compressed_url', $result);
        $this->assertArrayHasKey('thumbnail_url', $result);
        $this->assertArrayHasKey('origin_url', $result);

        $this->assertStringContainsString('storage/uploads/123/abc123.jpg', $result['compressed_url']);
        $this->assertStringContainsString('storage/uploads/123/abc123-thumb.jpg', $result['thumbnail_url']);
        $this->assertStringContainsString('storage/uploads/123/abc123-origin.jpg', $result['origin_url']);
    }

    public function test_get_public_urls_with_different_extensions()
    {
        $userId = '456';
        $filenames = [
            'compressed_filename' => 'def456.png',
            'thumbnail_filename' => 'def456-thumb.png',
            'origin_filename' => 'def456-origin.png',
        ];

        $result = $this->fileStorageService->getPublicUrls($userId, $filenames);

        $this->assertStringContainsString('storage/uploads/456/def456.png', $result['compressed_url']);
        $this->assertStringContainsString('storage/uploads/456/def456-thumb.png', $result['thumbnail_url']);
        $this->assertStringContainsString('storage/uploads/456/def456-origin.png', $result['origin_url']);
    }

    public function test_store_file_generates_unique_basenames()
    {
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');
        $directory = storage_path('app/public/uploads/1');

        $result1 = $this->fileStorageService->storeFile($file1, $directory);
        $result2 = $this->fileStorageService->storeFile($file2, $directory);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertNotEquals($result1['basename'], $result2['basename']);
    }

    public function test_store_file_rejects_disallowed_extension()
    {
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertFalse($result['success']);
        $this->assertSame('File validation failed', $result['message']);
        $this->assertStringContainsString('File type not allowed', $result['errors'][0]);
    }

    public function test_store_file_rejects_invalid_image_contents()
    {
        $file = UploadedFile::fake()->create('broken.jpg', 10, 'image/jpeg');
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertFalse($result['success']);
        $this->assertSame('File validation failed', $result['message']);
        $this->assertContains('File is not a valid image', $result['errors']);
    }

    public function test_delete_file_removes_existing_file()
    {
        $filePath = storage_path('app/public/uploads/1/delete-me.txt');
        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        file_put_contents($filePath, 'delete me');

        $result = $this->fileStorageService->deleteFile($filePath);

        $this->assertTrue($result['success']);
        $this->assertFileDoesNotExist($filePath);
    }

    public function test_delete_file_returns_error_for_missing_path()
    {
        $result = $this->fileStorageService->deleteFile(storage_path('app/public/uploads/1/missing.txt'));

        $this->assertFalse($result['success']);
        $this->assertSame('File not found', $result['message']);
    }

    public function test_delete_user_files_removes_all_related_variants()
    {
        $directory = storage_path('app/public/uploads/42');
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $files = [
            "{$directory}/avatar.jpg",
            "{$directory}/avatar-thumb.jpg",
            "{$directory}/avatar-origin.jpg",
            "{$directory}/avatar.png",
            "{$directory}/avatar-thumb.png",
        ];

        foreach ($files as $file) {
            file_put_contents($file, 'content');
        }

        $result = $this->fileStorageService->deleteUserFiles('42', 'avatar');

        $this->assertTrue($result['success']);
        $this->assertSame(5, $result['deleted_files']);

        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }
}
