<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\MusicController;
use Tests\TestCase;

class MusicControllerUnitTest extends TestCase
{
    private MusicController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MusicController;
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
}
