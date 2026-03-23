<?php

namespace Tests\Feature;

use App\Services\UpyunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_index_returns_music_list_from_upyun(): void
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');
        config()->set('services.upyun.domain', 'https://cdn.example.com');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => true,
                'files' => [
                    ['name' => 'first.mp3', 'type' => 'audio/mp3', 'length' => 1024],
                    ['name' => 'second.ogg', 'type' => 'audio/ogg', 'length' => 2048],
                    ['name' => 'ignore.txt', 'type' => 'text/plain', 'length' => 128],
                    ['name' => 'subdir', 'type' => 'folder', 'length' => 0],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->times(2)->andReturnUsing(
                fn (string $path): string => 'https://cdn.example.com' . $path
            );
        });

        $response = $this->getJson('/api/musics');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'first')
            ->assertJsonPath('0.path', 'https://cdn.example.com/music/first.mp3')
            ->assertJsonPath('0.size', 1024)
            ->assertJsonPath('0.extension', 'mp3')
            ->assertJsonPath('1.name', 'second')
            ->assertJsonPath('1.extension', 'ogg');
    }

    public function test_index_returns_service_unavailable_when_upyun_is_not_configured(): void
    {
        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        $response = $this->getJson('/api/musics');

        $response->assertStatus(503)
            ->assertJson(['error' => '又拍云未配置']);
    }

    public function test_index_uses_cached_upyun_music_list(): void
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');
        config()->set('services.upyun.domain', 'https://cdn.example.com');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->twice()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => true,
                'files' => [
                    ['name' => 'cached.mp3', 'type' => 'audio/mp3', 'length' => 4096],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->once()->with('/music/cached.mp3')->andReturn(
                'https://cdn.example.com/music/cached.mp3'
            );
        });

        $firstResponse = $this->getJson('/api/musics');
        $secondResponse = $this->getJson('/api/musics');

        $firstResponse->assertOk()->assertJsonCount(1);
        $secondResponse->assertOk()->assertJsonCount(1);
        $this->assertSame($firstResponse->json(), $secondResponse->json());
    }
}
