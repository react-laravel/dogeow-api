<?php

namespace Tests\Feature\Controllers\Api;

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

    public function test_index_returns_audio_files_only_from_upyun()
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
                    ['name' => 'audio1.mp3', 'type' => 'audio/mp3', 'length' => 1024],
                    ['name' => 'audio2.ogg', 'type' => 'audio/ogg', 'length' => 2048],
                    ['name' => 'audio3.flac', 'type' => 'audio/flac', 'length' => 3072],
                    ['name' => 'cover.jpg', 'type' => 'image/jpeg', 'length' => 256],
                    ['name' => 'nested', 'type' => 'folder', 'length' => 0],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->times(3)->andReturnUsing(
                fn (string $path): string => 'https://cdn.example.com' . $path
            );
        });

        $response = $this->getJson('/api/musics');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(3, $data);
        $this->assertSame(['audio1', 'audio2', 'audio3'], array_column($data, 'name'));
        $this->assertSame(['mp3', 'ogg', 'flac'], array_column($data, 'extension'));
    }

    public function test_index_encodes_special_characters_in_upyun_paths()
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
                    ['name' => 'test file 你好.mp3', 'type' => 'audio/mp3', 'length' => 1234],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->once()->with('/music/test%20file%20%E4%BD%A0%E5%A5%BD.mp3')->andReturn(
                'https://cdn.example.com/music/test%20file%20%E4%BD%A0%E5%A5%BD.mp3'
            );
        });

        $response = $this->getJson('/api/musics');

        $response->assertOk()
            ->assertJsonPath('0.name', 'test file 你好')
            ->assertJsonPath('0.path', 'https://cdn.example.com/music/test%20file%20%E4%BD%A0%E5%A5%BD.mp3');
    }

    public function test_index_returns_server_error_when_upyun_listing_fails()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => false,
                'message' => '又拍云目录读取失败',
            ]);
        });

        $response = $this->getJson('/api/musics');

        $response->assertStatus(500);
    }

    public function test_lyrics_returns_matching_lrc_content_from_upyun()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('readFile')
                ->once()
                ->with('/music/test file 你好.lrc')
                ->andReturn([
                    'success' => true,
                    'body' => "[00:01.00]第一句歌词\n[00:02.00]第二句歌词",
                    'content_type' => 'text/plain',
                ]);
        });

        $response = $this->get('/api/musics/lyrics/test%20file%20%E4%BD%A0%E5%A5%BD.mp3');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('第一句歌词');
    }

    public function test_lyrics_returns_404_when_matching_lrc_is_missing()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('readFile')
                ->once()
                ->with('/music/missing.lrc')
                ->andReturn([
                    'success' => false,
                    'status' => 404,
                    'message' => 'not found',
                ]);
        });

        $response = $this->get('/api/musics/lyrics/missing.mp3');

        $response->assertNotFound()
            ->assertJson(['error' => '歌词不存在']);
    }
}
