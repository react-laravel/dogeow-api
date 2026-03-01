<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test music directory
        $musicDir = public_path('musics');
        if (! File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $musicDir = public_path('musics');
        if (File::exists($musicDir)) {
            File::deleteDirectory($musicDir);
        }

        parent::tearDown();
    }

    public function test_index_returns_music_list(): void
    {
        // Create test music files
        $musicDir = public_path('musics');
        $testFiles = [
            'test-song-1.mp3' => 'test content 1',
            'test-song-2.ogg' => 'test content 2',
            'test-song-3.wav' => 'test content 3',
            'invalid-file.txt' => 'not a music file',
        ];

        foreach ($testFiles as $filename => $content) {
            File::put($musicDir . '/' . $filename, $content);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'name',
                    'path',
                    'size',
                    'extension',
                ],
            ]);

        $responseData = $response->json();

        // Should return 3 music files (excluding .txt file)
        $this->assertCount(3, $responseData);

        // Verify music files are included
        $musicNames = collect($responseData)->pluck('name')->toArray();
        $this->assertContains('test-song-1', $musicNames);
        $this->assertContains('test-song-2', $musicNames);
        $this->assertContains('test-song-3', $musicNames);

        // Verify .txt file is excluded
        $this->assertNotContains('invalid-file', $musicNames);

        // Verify file paths are correct
        foreach ($responseData as $music) {
            $this->assertStringStartsWith('/musics/', $music['path']);
            $this->assertContains($music['extension'], ['mp3', 'ogg', 'wav']);
            $this->assertIsInt($music['size']);
        }
    }

    public function test_index_returns_404_when_music_directory_does_not_exist(): void
    {
        // Remove music directory
        $musicDir = public_path('musics');
        if (File::exists($musicDir)) {
            File::deleteDirectory($musicDir);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(404)
            ->assertJson([
                'error' => '音乐目录不存在',
            ]);
    }

    public function test_index_returns_empty_array_when_no_music_files(): void
    {
        // Ensure music directory exists but is empty
        $musicDir = public_path('musics');
        if (! File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_index_only_includes_supported_audio_formats(): void
    {
        $musicDir = public_path('musics');
        $testFiles = [
            'valid-1.mp3' => 'content',
            'valid-2.ogg' => 'content',
            'valid-3.wav' => 'content',
            'valid-4.flac' => 'content',
            'valid-5.m4a' => 'content',
            'valid-6.aac' => 'content',
            'invalid-1.txt' => 'content',
            'invalid-2.jpg' => 'content',
            'invalid-3.pdf' => 'content',
        ];

        foreach ($testFiles as $filename => $content) {
            File::put($musicDir . '/' . $filename, $content);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should return 6 valid audio files
        $this->assertCount(6, $responseData);

        // Verify only supported formats are included
        $extensions = collect($responseData)->pluck('extension')->toArray();
        $supportedFormats = ['mp3', 'ogg', 'wav', 'flac', 'm4a', 'aac'];

        foreach ($extensions as $extension) {
            $this->assertContains($extension, $supportedFormats);
        }

        // Verify invalid files are excluded
        $names = collect($responseData)->pluck('name')->toArray();
        $this->assertNotContains('invalid-1', $names);
        $this->assertNotContains('invalid-2', $names);
        $this->assertNotContains('invalid-3', $names);
    }

    public function test_index_handles_case_insensitive_extensions(): void
    {
        $musicDir = public_path('musics');
        $testFiles = [
            'song-1.MP3' => 'content',
            'song-2.Ogg' => 'content',
            'song-3.WAV' => 'content',
        ];

        foreach ($testFiles as $filename => $content) {
            File::put($musicDir . '/' . $filename, $content);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should return 3 files with case-insensitive extensions
        $this->assertCount(3, $responseData);

        // Verify extensions are normalized to lowercase
        foreach ($responseData as $music) {
            $this->assertContains($music['extension'], ['mp3', 'ogg', 'wav']);
        }
    }

    public function test_index_handles_files_without_extensions(): void
    {
        $musicDir = public_path('musics');
        $testFiles = [
            'song-without-extension' => 'content',
            'song-with-dot.' => 'content',
            'valid-song.mp3' => 'content',
        ];

        foreach ($testFiles as $filename => $content) {
            File::put($musicDir . '/' . $filename, $content);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should only return the valid .mp3 file
        $this->assertCount(1, $responseData);
        $this->assertEquals('valid-song', $responseData[0]['name']);
    }
}
