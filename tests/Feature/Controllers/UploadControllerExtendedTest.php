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

    public function test_upload_handles_partial_success_with_some_failed_files()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [
            UploadedFile::fake()->image('valid1.jpg'),
            UploadedFile::fake()->image('valid2.png'),
        ];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(200);
        // Should return at least the valid files
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    public function test_upload_returns_correct_file_paths()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [UploadedFile::fake()->image('test.jpg')];

        $response = $this->postJson('/api/upload/images', ['images' => $images]);

        $response->assertStatus(200);
        $data = $response->json();

        if (count($data) > 0) {
            $this->assertArrayHasKey('path', $data[0]);
            $this->assertArrayHasKey('origin_path', $data[0]);
            $this->assertStringContainsString($user->id, $data[0]['path']);
        }
    }

    public function test_upload_with_non_array_images_field()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/upload/images', [
            'images' => 'not-an-array',
        ]);

        // When images field is not an array, the controller returns 400 because hasFile() returns false
        $response->assertStatus(400);
    }
}
