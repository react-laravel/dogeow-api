<?php

namespace Tests\Feature\Controllers;

use App\Jobs\RemoveBackgroundJob;
use App\Models\User;
use App\Services\File\ImageProcessingService;
use App\Services\File\RmbgStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadRmbgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->mock(ImageProcessingService::class, function ($mock): void {
            $mock->shouldReceive('processImage')->andReturn(['success' => true]);
        });
    }

    public function test_upload_with_remove_bg_dispatches_job_and_returns_pending_status(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $images = [UploadedFile::fake()->image('test.jpg')];

        $response = $this->post('/api/upload/images', [
            'images' => $images,
            'remove_bg' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('0.rmbg_status', 'pending');
        $response->assertJsonStructure([
            0 => ['path', 'origin_path', 'url', 'origin_url', 'thumbnail_url', 'rmbg_status'],
        ]);

        Queue::assertPushed(RemoveBackgroundJob::class, function (RemoveBackgroundJob $job) use ($user): bool {
            return $job->userId === $user->id
                && str_starts_with($job->compressedPath, "uploads/{$user->id}/")
                && str_contains($job->originPath, '-origin.');
        });
    }

    public function test_rmbg_status_endpoint_returns_cached_status_for_owner(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $path = "uploads/{$user->id}/abc.jpg";
        app(RmbgStatusService::class)->setDone($path, [
            'path' => "uploads/{$user->id}/abc.png",
            'url' => 'https://example.com/storage/uploads/' . $user->id . '/abc.png',
            'thumbnail_url' => 'https://example.com/storage/uploads/' . $user->id . '/abc-thumb.png',
        ]);

        $response = $this->getJson('/api/upload/images/rmbg-status?path=' . urlencode($path));

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'done');
        $response->assertJsonPath('path', "uploads/{$user->id}/abc.png");
    }

    public function test_rmbg_status_endpoint_rejects_other_users_path(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/upload/images/rmbg-status?path=' . urlencode('uploads/999/abc.jpg'));

        $response->assertStatus(403);
    }

    public function test_upload_without_remove_bg_does_not_dispatch_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/upload/images', [
            'images' => [UploadedFile::fake()->image('test.jpg')],
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissing(['rmbg_status' => 'pending']);
        Queue::assertNothingPushed();
    }

    public function test_rmbg_status_returns_unknown_when_not_cached(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $path = "uploads/{$user->id}/missing.jpg";
        $response = $this->getJson('/api/upload/images/rmbg-status?path=' . urlencode($path));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'unknown']);
    }
}
