<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Services\UpyunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisionUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Ensure storage directories exist
        $paths = [
            storage_path('app'),
            storage_path('app/vision-temp'),
        ];
        foreach ($paths as $path) {
            if (! file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    public function test_upload_vision_image_success(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->image('test.jpg', 800, 600);

        $this->mock(UpyunService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.jpg',
            ]);
        });

        $response = $this->postJson('/api/vision/upload', [
            'image' => $image,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'url' => 'https://example.com/vision/test.jpg',
        ]);
    }

    public function test_upload_vision_image_with_png(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->image('test.png', 800, 600);

        $this->mock(UpyunService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.png',
            ]);
        });

        $response = $this->postJson('/api/vision/upload', [
            'image' => $image,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_upload_vision_image_with_gif(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->image('test.gif', 800, 600);

        $this->mock(UpyunService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.gif',
            ]);
        });

        $response = $this->postJson('/api/vision/upload', [
            'image' => $image,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_upload_vision_image_with_webp(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->image('test.webp', 800, 600);

        $this->mock(UpyunService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.webp',
            ]);
        });

        $response = $this->postJson('/api/vision/upload', [
            'image' => $image,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_upload_vision_image_is_public(): void
    {
        // Vision upload is a public endpoint (no auth required)
        $image = UploadedFile::fake()->image('test.jpg', 800, 600);

        $this->mock(UpyunService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'success' => true,
                'url' => 'https://example.com/vision/test.jpg',
            ]);
        });

        $response = $this->postJson('/api/vision/upload', [
            'image' => $image,
        ]);

        // Should succeed without authentication
        $response->assertStatus(200);
    }

    public function test_upload_vision_image_requires_image_field(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/vision/upload', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_upload_vision_image_validates_file_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->postJson('/api/vision/upload', [
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_upload_vision_image_validates_max_size(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a file larger than 20MB
        $file = UploadedFile::fake()->image('large.jpg')->size(25000);

        $response = $this->postJson('/api/vision/upload', [
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_upload_vision_image_handles_upload_failure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $image = UploadedFile::fake()->image('test.jpg', 800, 600);

        $this->mock(UpyunService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn([
                'success' => false,
                'message' => 'Upload failed',
            ]);
        });

        $response = $this->postJson('/api/vision/upload', [
            'image' => $image,
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Upload failed',
        ]);
    }

    public function test_upload_vision_image_handles_invalid_file(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a temporary file that appears invalid
        $tmpPath = tempnam(sys_get_temp_dir(), 'invalid');
        file_put_contents($tmpPath, 'not-an-image');

        $invalidImage = new UploadedFile(
            $tmpPath,
            'invalid.jpg',
            'image/jpeg',
            null,
            true
        );

        // Validation fails first (not a valid image), returns 422
        $response = $this->postJson('/api/vision/upload', [
            'image' => $invalidImage,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }
}
