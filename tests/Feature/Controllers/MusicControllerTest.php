<?php

namespace Tests\Feature\Controllers;

use App\Services\UpyunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function it_can_get_music_list()
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
                    ['name' => 'test1.mp3', 'type' => 'audio/mp3', 'length' => 14],
                    ['name' => 'test2.ogg', 'type' => 'audio/ogg', 'length' => 14],
                    ['name' => 'test3.wav', 'type' => 'audio/wav', 'length' => 14],
                    ['name' => 'ignore.txt', 'type' => 'text/plain', 'length' => 17],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->times(3)->andReturnUsing(
                fn (string $path): string => 'https://cdn.example.com' . $path
            );
        });

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('path', $data[0]);
        $this->assertArrayHasKey('size', $data[0]);
        $this->assertArrayHasKey('extension', $data[0]);
    }

    #[Test]
    public function it_returns_error_when_upyun_is_not_configured()
    {
        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(false);
        });

        $response = $this->get('/api/musics');

        $response->assertStatus(503);
        $response->assertJson([
            'error' => '又拍云未配置',
        ]);
    }

    #[Test]
    public function it_handles_empty_upyun_music_directory()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => true,
                'files' => [],
            ]);
        });

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $this->assertSame([], $response->json());
    }

    #[Test]
    public function it_filters_only_audio_files()
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
                    ['name' => 'audio1.mp3', 'type' => 'audio/mp3', 'length' => 100],
                    ['name' => 'audio2.ogg', 'type' => 'audio/ogg', 'length' => 200],
                    ['name' => 'document.pdf', 'type' => 'application/pdf', 'length' => 300],
                    ['name' => 'image.jpg', 'type' => 'image/jpeg', 'length' => 400],
                    ['name' => 'audio3.flac', 'type' => 'audio/flac', 'length' => 500],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->times(3)->andReturnUsing(
                fn (string $path): string => 'https://cdn.example.com' . $path
            );
        });

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data);
        $filenames = array_column($data, 'name');
        $this->assertContains('audio1', $filenames);
        $this->assertContains('audio2', $filenames);
        $this->assertContains('audio3', $filenames);
        $this->assertNotContains('document', $filenames);
        $this->assertNotContains('image', $filenames);
    }

    #[Test]
    public function it_returns_correct_file_information()
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
                    ['name' => 'test-song.mp3', 'type' => 'audio/mp3', 'length' => 18],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->once()->with('/music/test-song.mp3')->andReturn(
                'https://cdn.example.com/music/test-song.mp3'
            );
        });

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $music = $data[0];

        $this->assertEquals('test-song', $music['name']);
        $this->assertEquals('https://cdn.example.com/music/test-song.mp3', $music['path']);
        $this->assertEquals('mp3', $music['extension']);
        $this->assertEquals(18, $music['size']);
    }
}
