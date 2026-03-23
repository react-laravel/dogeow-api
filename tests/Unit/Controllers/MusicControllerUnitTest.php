<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\MusicController;
use App\Services\UpyunService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MusicControllerUnitTest extends TestCase
{
    private MusicController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->controller = new MusicController($this->mockUpyunService());
    }

    public function test_get_mime_type_returns_audio_mpeg_for_mp3(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $mimeType = $method->invoke($this->controller, '/path/to/file.mp3');

        $this->assertEquals('audio/mpeg', $mimeType);
    }

    public function test_get_mime_type_returns_audio_ogg_for_ogg(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $mimeType = $method->invoke($this->controller, '/path/to/file.ogg');

        $this->assertEquals('audio/ogg', $mimeType);
    }

    public function test_get_mime_type_returns_audio_wav_for_wav(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $mimeType = $method->invoke($this->controller, '/path/to/file.wav');

        $this->assertEquals('audio/wav', $mimeType);
    }

    public function test_get_mime_type_returns_audio_flac_for_flac(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $mimeType = $method->invoke($this->controller, '/path/to/file.flac');

        $this->assertEquals('audio/flac', $mimeType);
    }

    public function test_get_mime_type_returns_default_for_unknown(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $mimeType = $method->invoke($this->controller, '/path/to/file.xyz');

        $this->assertEquals('application/octet-stream', $mimeType);
    }

    public function test_index_returns_503_when_upyun_is_not_configured(): void
    {
        $controller = new MusicController(new class extends UpyunService
        {
            public function __construct() {}

            public function isConfigured(): bool
            {
                return false;
            }
        });

        $response = $controller->index();

        $this->assertEquals(503, $response->getStatusCode());
        $this->assertSame(['error' => '又拍云未配置'], json_decode($response->getContent(), true));
    }

    public function test_index_lists_supported_audio_files_only(): void
    {
        $response = $this->controller->index();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data);
        $this->assertSame(['first', 'second'], array_column($data, 'name'));
        $this->assertSame(
            ['https://cdn.example.com/music/first.mp3', 'https://cdn.example.com/music/second.ogg'],
            array_column($data, 'path')
        );
    }

    public function test_download_returns_404_for_missing_file(): void
    {
        $response = $this->controller->download('missing.mp3');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertSame(['error' => '文件不存在'], json_decode($response->getContent(), true));
    }

    public function test_download_returns_partial_content_when_range_header_is_present(): void
    {
        $musicDir = public_path('musics');
        File::ensureDirectoryExists($musicDir);
        file_put_contents($musicDir . '/sample.mp3', 'abcdef');

        $request = Request::create('/musics/sample.mp3', 'GET', [], [], [], ['HTTP_RANGE' => 'bytes=1-3']);
        $this->app->instance('request', $request);

        $response = $this->controller->download('sample.mp3');

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertSame('bytes 1-3/6', $response->headers->get('Content-Range'));
        $this->assertSame('3', $response->headers->get('Content-Length'));
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertSame('bcd', $content);
    }

    public function test_download_clamps_range_values_to_file_bounds(): void
    {
        $musicDir = public_path('musics');
        File::ensureDirectoryExists($musicDir);
        file_put_contents($musicDir . '/clamped.mp3', 'abcdef');

        $request = Request::create('/musics/clamped.mp3', 'GET', [], [], [], ['HTTP_RANGE' => 'bytes=99-200']);
        $this->app->instance('request', $request);

        $response = $this->controller->download('clamped.mp3');

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertSame('bytes 5-5/6', $response->headers->get('Content-Range'));
        $this->assertSame('1', $response->headers->get('Content-Length'));
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertSame('f', $content);
    }

    public function test_download_supports_open_ended_range_headers(): void
    {
        $musicDir = public_path('musics');
        File::ensureDirectoryExists($musicDir);
        file_put_contents($musicDir . '/open-ended.mp3', 'abcdef');

        $request = Request::create('/musics/open-ended.mp3', 'GET', [], [], [], ['HTTP_RANGE' => 'bytes=-2']);
        $this->app->instance('request', $request);

        $response = $this->controller->download('open-ended.mp3');

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertSame('bytes 0-2/6', $response->headers->get('Content-Range'));
        $this->assertSame('3', $response->headers->get('Content-Length'));
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertSame('abc', $content);
    }

    public function test_download_returns_full_file_response_without_range_header(): void
    {
        $musicDir = public_path('musics');
        File::ensureDirectoryExists($musicDir);
        $filePath = $musicDir . '/full.mp3';
        file_put_contents($filePath, 'abcdef');

        $request = Request::create('/musics/full.mp3', 'GET');
        $this->app->instance('request', $request);

        $response = $this->controller->download('full.mp3');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('audio/mpeg', $response->headers->get('content-type'));
        $this->assertSame((string) filesize($filePath), $response->headers->get('content-length'));
    }

    private function mockUpyunService(): UpyunService
    {
        return new class extends UpyunService
        {
            public function __construct() {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function listDirectory(string $remoteDirectory, int $limit = 1000): array
            {
                return [
                    'success' => true,
                    'files' => [
                        ['name' => 'first.mp3', 'type' => 'audio/mp3', 'length' => 3],
                        ['name' => 'second.ogg', 'type' => 'audio/ogg', 'length' => 4],
                        ['name' => 'notes.txt', 'type' => 'text/plain', 'length' => 10],
                        ['name' => 'subdir', 'type' => 'folder', 'length' => 0],
                    ],
                ];
            }

            public function buildPublicUrl(string $remotePath): string
            {
                return 'https://cdn.example.com' . $remotePath;
            }
        };
    }
}
