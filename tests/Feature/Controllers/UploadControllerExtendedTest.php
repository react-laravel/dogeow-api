<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Services\File\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadControllerExtendedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_upload_when_directory_creation_fails()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(FileStorageService::class, function ($mock) {
            $mock->shouldReceive('createUserDirectory')
                ->andReturn(['success' => false, 'message' => 'Failed to create directory']);
        });

        $images = [UploadedFile::fake()->image('test.jpg')];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(500);
        $response->assertJson(['message' => 'Failed to create directory']);
    }

    public function test_upload_with_multiple_files_returns_uploaded_entries()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [
            UploadedFile::fake()->image('valid1.jpg'),
            UploadedFile::fake()->image('valid2.png'),
        ];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertIsArray($responseData);
        $this->assertCount(2, $responseData);
    }

    public function test_upload_returns_correct_file_paths()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [UploadedFile::fake()->image('test.jpg')];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('path', $data[0]);
        $this->assertArrayHasKey('origin_path', $data[0]);
        $this->assertStringContainsString((string) $user->id, $data[0]['path']);
    }

    public function test_upload_with_non_array_images_field()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/upload/images', [
            'images' => 'not-an-array',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => '没有找到上传的图片文件']);
    }

    public function test_upload_returns_default_message_when_directory_creation_fails_without_message()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(FileStorageService::class, function ($mock) {
            $mock->shouldReceive('createUserDirectory')
                ->andReturn(['success' => false]);
        });

        $images = [UploadedFile::fake()->image('test.jpg')];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(500);
        $response->assertJson(['message' => '创建用户目录失败']);
    }

    public function test_upload_with_partial_store_failures_returns_only_successful_entries()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(FileStorageService::class, function ($mock) use ($user) {
            $mock->shouldReceive('createUserDirectory')
                ->once()
                ->andReturn([
                    'success' => true,
                    'directory_path' => 'uploads/' . $user->id,
                ]);

            $call = 0;
            $mock->shouldReceive('storeFile')
                ->twice()
                ->andReturnUsing(function () use (&$call, $user) {
                    $call++;
                    if ($call === 1) {
                        return [
                            'success' => false,
                            'message' => 'first failed',
                        ];
                    }

                    return [
                        'success' => true,
                        'origin_path' => 'uploads/' . $user->id . '/origin-second.jpg',
                        'compressed_path' => 'uploads/' . $user->id . '/compressed-second.jpg',
                        'origin_filename' => 'origin-second.jpg',
                        'compressed_filename' => 'compressed-second.jpg',
                    ];
                });

            $mock->shouldReceive('getPublicUrls')
                ->once()
                ->andReturn([
                    'origin_url' => 'https://example.com/origin-second.jpg',
                    'compressed_url' => 'https://example.com/compressed-second.jpg',
                ]);
        });

        $images = [
            UploadedFile::fake()->image('first.jpg', 100, 100),
            UploadedFile::fake()->image('second.jpg', 100, 100),
        ];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.origin_path', 'uploads/' . $user->id . '/origin-second.jpg');
        $response->assertJsonPath('0.path', 'uploads/' . $user->id . '/compressed-second.jpg');
    }

    public function test_upload_with_successful_image_processing()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(FileStorageService::class, function ($mock) use ($user) {
            $mock->shouldReceive('createUserDirectory')
                ->once()
                ->andReturn([
                    'success' => true,
                    'directory_path' => 'uploads/' . $user->id,
                ]);

            $mock->shouldReceive('storeFile')
                ->once()
                ->andReturn([
                    'success' => true,
                    'origin_path' => 'uploads/' . $user->id . '/origin.jpg',
                    'compressed_path' => 'uploads/' . $user->id . '/compressed.jpg',
                    'origin_filename' => 'origin.jpg',
                    'compressed_filename' => 'compressed.jpg',
                ]);

            $mock->shouldReceive('getPublicUrls')
                ->once()
                ->andReturn([
                    'origin_url' => 'https://example.com/origin.jpg',
                    'compressed_url' => 'https://example.com/compressed.jpg',
                ]);
        });

        $images = [UploadedFile::fake()->image('test.jpg')];
        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $this->assertArrayHasKey('path', $response->json()[0]);
        $this->assertArrayHasKey('url', $response->json()[0]);
    }
}
