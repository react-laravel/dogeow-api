<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_upload_batch_images_with_valid_files()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [
            UploadedFile::fake()->image('image1.jpg'),
            UploadedFile::fake()->image('image2.png'),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $images,
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

        $this->assertCount(2, $response->json());
    }

    public function test_upload_batch_images_without_files()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/upload/images', []);

        $response->assertStatus(400);
        $response->assertJson(['message' => '没有找到上传的图片文件']);
    }

    public function test_upload_batch_images_with_invalid_file_type()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $files = [
            UploadedFile::fake()->create('document.txt', 100),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $files,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_upload_batch_images_with_file_too_large()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a file larger than 20MB
        $files = [
            UploadedFile::fake()->image('large.jpg')->size(25000), // 25MB
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $files,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    public function test_upload_batch_images_with_mixed_valid_and_invalid_files()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $files = [
            UploadedFile::fake()->image('valid.jpg'),
            UploadedFile::fake()->create('invalid.txt', 100),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $files,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.1']);
    }

    public function test_upload_batch_images_unauthenticated()
    {
        $images = [
            UploadedFile::fake()->image('image.jpg'),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $images,
        ]);

        $response->assertStatus(401);
    }

    public function test_upload_batch_images_with_single_valid_file()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [
            UploadedFile::fake()->image('single.jpg'),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $images,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_upload_batch_images_with_empty_array()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/upload/images', [
            'images' => [],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => '没有找到上传的图片文件']);
    }

    public function test_upload_batch_images_with_different_image_formats()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [
            UploadedFile::fake()->image('image1.jpg'),
            UploadedFile::fake()->image('image2.png'),
            UploadedFile::fake()->image('image3.gif'),
            UploadedFile::fake()->image('image4.webp'),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $images,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(4);
    }

    public function test_upload_batch_images_with_unicode_filenames()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [
            UploadedFile::fake()->image('测试图片.jpg'),
            UploadedFile::fake()->image('image-测试.png'),
        ];

        $response = $this->postJson('/api/upload/images', [
            'images' => $images,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_upload_batch_images_with_large_number_of_files()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [];
        for ($i = 0; $i < 10; $i++) {
            $images[] = UploadedFile::fake()->image("image{$i}.jpg");
        }

        $response = $this->postJson('/api/upload/images', [
            'images' => $images,
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(10);
    }
}
