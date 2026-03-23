<?php

namespace Tests\Unit\Models\Repo;

use App\Models\Repo\RepositoryUpdate;
use App\Models\Repo\WatchedRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepositoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function belongs_to_watched_repository(): void
    {
        // Arrange
        $repository = WatchedRepository::factory()->create();

        // Act
        $update = RepositoryUpdate::create([
            'watched_repository_id' => $repository->id,
            'source_type' => 'release',
            'source_id' => '123',
            'version' => '1.0.0',
            'title' => 'Test Release',
            'release_url' => 'https://example.com/release',
            'body' => 'Release notes',
            'published_at' => now(),
            'metadata' => ['key' => 'value'],
        ]);

        // Assert
        $this->assertInstanceOf(WatchedRepository::class, $update->watchedRepository);
        $this->assertEquals($repository->id, $update->watchedRepository->id);
    }

    #[Test]
    public function metadata_is_cast_to_array(): void
    {
        // Arrange
        $repository = WatchedRepository::factory()->create();
        $metadata = ['key' => 'value', 'nested' => ['a' => 1]];

        // Act
        $update = RepositoryUpdate::create([
            'watched_repository_id' => $repository->id,
            'source_type' => 'release',
            'source_id' => '123',
            'version' => '1.0.0',
            'title' => 'Test Release',
            'release_url' => 'https://example.com/release',
            'body' => 'Release notes',
            'published_at' => now(),
            'metadata' => $metadata,
        ]);

        // Assert
        $update->refresh();
        $this->assertIsArray($update->metadata);
        $this->assertEquals('value', $update->metadata['key']);
        $this->assertEquals(1, $update->metadata['nested']['a']);
    }

    #[Test]
    public function published_at_is_cast_to_datetime(): void
    {
        // Arrange
        $repository = WatchedRepository::factory()->create();
        $publishedAt = now();

        // Act
        $update = RepositoryUpdate::create([
            'watched_repository_id' => $repository->id,
            'source_type' => 'release',
            'source_id' => '123',
            'version' => '1.0.0',
            'title' => 'Test Release',
            'release_url' => 'https://example.com/release',
            'body' => 'Release notes',
            'published_at' => $publishedAt,
            'metadata' => [],
        ]);

        // Assert
        $update->refresh();
        $this->assertInstanceOf(\Carbon\Carbon::class, $update->published_at);
        $this->assertEquals($publishedAt->toDateTimeString(), $update->published_at->toDateTimeString());
    }

    #[Test]
    public function fillable_contains_expected_fields(): void
    {
        // Arrange
        $update = new RepositoryUpdate;

        // Assert
        $this->assertEquals([
            'watched_repository_id',
            'source_type',
            'source_id',
            'version',
            'title',
            'release_url',
            'body',
            'ai_summary',
            'published_at',
            'metadata',
        ], $update->getFillable());
    }
}
