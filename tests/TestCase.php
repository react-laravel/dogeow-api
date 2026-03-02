<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // make sure storage directories exist for file-related tests
        $paths = [
            storage_path('app'),
            storage_path('app/public'),
            storage_path('app/public/uploads'),
            storage_path('logs'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }
        }

        // also ensure music directory used by MusicController exists
        $musicDir = public_path('musics');
        if (! is_dir($musicDir)) {
            @mkdir($musicDir, 0755, true);
        }
    }
}
