<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Services\File\FileStorageService;
use App\Services\File\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[Test]
    public function it_can_upload_batch_images()
    {
        $user = User::factory()->create();

        $image1 = UploadedFile::fake()->image('test1.jpg', 100, 100);
        $image2 = UploadedFile::fake()->image('test2.png', 200, 200);

        $response = $this->actingAs($user)
            ->post('/api/upload/images', [
                'images' => [$image1, $image2],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'path',
                'origin_path',
                'url',
                'origin_url',
            ],
        ]);
    }

    #[Test]
    public function it_validates_image_files()
    {
        $user = User::factory()->create();

        $textFile = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->actingAs($user)
            ->postJson('/api/upload/images', [
                'images' => [$textFile],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    #[Test]
    public function it_validates_image_size()
    {
        $user = User::factory()->create();

        // 创建一个超过20MB的图片文件
        $largeImage = UploadedFile::fake()->create('large.jpg', 25000); // 25MB

        $response = $this->actingAs($user)
            ->postJson('/api/upload/images', [
                'images' => [$largeImage],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    #[Test]
    public function it_returns_error_when_no_images_provided()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/upload/images', []);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => '没有找到上传的图片文件',
        ]);
    }

    #[Test]
    public function it_handles_invalid_uploaded_files()
    {
        $user = User::factory()->create();

        $tmpPath = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tmpPath, 'invalid-image');

        // 模拟无效的文件上传
        $invalidImage = new UploadedFile(
            $tmpPath,
            'invalid.jpg',
            'image/jpeg',
            UPLOAD_ERR_PARTIAL,
            true
        );

        $response = $this->actingAs($user)
            ->postJson('/api/upload/images', [
                'images' => [$invalidImage],
            ]);

        // 上传错误返回 422 验证错误
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    #[Test]
    public function it_handles_mixed_valid_and_invalid_files()
    {
        $user = User::factory()->create();

        $validImage = UploadedFile::fake()->image('valid.jpg', 100, 100);
        $textFile = UploadedFile::fake()->create('invalid.txt', 100);

        $response = $this->actingAs($user)
            ->postJson('/api/upload/images', [
                'images' => [$validImage, $textFile],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.1']);
    }

    #[Test]
    public function it_creates_user_directory_structure()
    {
        $user = User::factory()->create();

        $image = UploadedFile::fake()->image('test.jpg', 100, 100);

        $this->actingAs($user)
            ->post('/api/upload/images', [
                'images' => [$image],
            ]);

        // 验证用户目录是否被创建
        $this->assertTrue(Storage::disk('public')->exists('uploads/' . $user->id));
    }

    #[Test]
    public function it_returns_proper_error_message_for_upload_failures()
    {
        $user = User::factory()->create();

        // 模拟文件存储服务失败
        $this->mock(FileStorageService::class, function ($mock) {
            $mock->shouldReceive('createUserDirectory')->andThrow(new \Exception('Storage service error'));
        });

        $image = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->actingAs($user)
            ->post('/api/upload/images', [
                'images' => [$image],
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'message' => '图片上传失败: Storage service error',
        ]);
    }

    #[Test]
    public function it_handles_image_processing_failures()
    {
        $user = User::factory()->create();

        // 模拟图片处理服务失败
        $this->mock(ImageProcessingService::class, function ($mock) {
            $mock->shouldReceive('processImage')->andReturn([
                'success' => false,
                'error' => 'Image processing failed',
            ]);
        });

        $image = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->actingAs($user)
            ->post('/api/upload/images', [
                'images' => [$image],
            ]);

        $response->assertStatus(200);
        // 应该返回空数组，因为处理失败
        $response->assertJson([]);
    }
}
