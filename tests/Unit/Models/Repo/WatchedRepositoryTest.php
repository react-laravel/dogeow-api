<?php

namespace Tests\Unit\Models\Repo;

use App\Models\Repo\RepositoryUpdate;
use App\Models\Repo\WatchedRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WatchedRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function belongs_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $repository = WatchedRepository::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'full_name' => 'test-owner/test-repo',
            'html_url' => 'https://github.com/test-owner/test-repo',
            'default_branch' => 'main',
            'language' => 'PHP',
            'ecosystem' => null,
            'package_name' => null,
            'manifest_path' => null,
            'latest_version' => '1.0.0',
            'latest_source_type' => 'release',
            'latest_release_url' => 'https://github.com/test-owner/test-repo/releases/tag/1.0.0',
            'description' => 'Test repository',
            'latest_release_published_at' => now(),
            'last_checked_at' => now(),
            'last_error' => null,
            'metadata' => [],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $repository->user);
        $this->assertEquals($user->id, $repository->user->id);
    }

    #[Test]
    public function has_many_updates(): void
    {
        // Arrange
        $user = User::factory()->create();
        $repository = WatchedRepository::factory()->create(['user_id' => $user->id]);
        $update1 = RepositoryUpdate::factory()->create([
            'watched_repository_id' => $repository->id,
            'version' => '1.0.0',
            'published_at' => now()->subDay(),
        ]);
        $update2 = RepositoryUpdate::factory()->create([
            'watched_repository_id' => $repository->id,
            'version' => '1.1.0',
            'published_at' => now(),
        ]);

        // Act
        $updates = $repository->updates;

        // Assert
        $this->assertCount(2, $updates);
        // Should be ordered by latest first (published_at desc)
        $this->assertEquals($update2->id, $updates->first()->id);
    }

    #[Test]
    public function has_one_latest_update(): void
    {
        // Arrange
        $user = User::factory()->create();
        $repository = WatchedRepository::factory()->create(['user_id' => $user->id]);
        RepositoryUpdate::factory()->create([
            'watched_repository_id' => $repository->id,
            'version' => '1.0.0',
            'published_at' => now()->subDay(),
        ]);
        $latestUpdate = RepositoryUpdate::factory()->create([
            'watched_repository_id' => $repository->id,
            'version' => '1.1.0',
            'published_at' => now(),
        ]);

        // Act
        $result = $repository->latestUpdate;

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($latestUpdate->id, $result->id);
    }

    #[Test]
    public function metadata_is_cast_to_array(): void
    {
        // Arrange
        $user = User::factory()->create();
        $metadata = ['stars' => 100, 'forks' => 10];

        // Act
        $repository = WatchedRepository::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'full_name' => 'test-owner/test-repo',
            'html_url' => 'https://github.com/test-owner/test-repo',
            'default_branch' => 'main',
            'language' => 'PHP',
            'ecosystem' => null,
            'package_name' => null,
            'manifest_path' => null,
            'latest_version' => '1.0.0',
            'latest_source_type' => 'release',
            'latest_release_url' => null,
            'description' => null,
            'latest_release_published_at' => null,
            'last_checked_at' => null,
            'last_error' => null,
            'metadata' => $metadata,
        ]);

        // Assert
        $repository->refresh();
        $this->assertIsArray($repository->metadata);
        $this->assertEquals(100, $repository->metadata['stars']);
    }

    #[Test]
    public function latest_release_published_at_is_cast_to_datetime(): void
    {
        // Arrange
        $user = User::factory()->create();
        $publishedAt = now();

        // Act
        $repository = WatchedRepository::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'full_name' => 'test-owner/test-repo',
            'html_url' => 'https://github.com/test-owner/test-repo',
            'default_branch' => 'main',
            'language' => 'PHP',
            'ecosystem' => null,
            'package_name' => null,
            'manifest_path' => null,
            'latest_version' => '1.0.0',
            'latest_source_type' => 'release',
            'latest_release_url' => null,
            'description' => null,
            'latest_release_published_at' => $publishedAt,
            'last_checked_at' => null,
            'last_error' => null,
            'metadata' => [],
        ]);

        // Assert
        $repository->refresh();
        $this->assertInstanceOf(\Carbon\Carbon::class, $repository->latest_release_published_at);
        $this->assertEquals($publishedAt->toDateTimeString(), $repository->latest_release_published_at->toDateTimeString());
    }

    #[Test]
    public function last_checked_at_is_cast_to_datetime(): void
    {
        // Arrange
        $user = User::factory()->create();
        $checkedAt = now();

        // Act
        $repository = WatchedRepository::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => 'test-owner',
            'repo' => 'test-repo',
            'full_name' => 'test-owner/test-repo',
            'html_url' => 'https://github.com/test-owner/test-repo',
            'default_branch' => 'main',
            'language' => 'PHP',
            'ecosystem' => null,
            'package_name' => null,
            'manifest_path' => null,
            'latest_version' => '1.0.0',
            'latest_source_type' => 'release',
            'latest_release_url' => null,
            'description' => null,
            'latest_release_published_at' => null,
            'last_checked_at' => $checkedAt,
            'last_error' => null,
            'metadata' => [],
        ]);

        // Assert
        $repository->refresh();
        $this->assertInstanceOf(\Carbon\Carbon::class, $repository->last_checked_at);
        $this->assertEquals($checkedAt->toDateTimeString(), $repository->last_checked_at->toDateTimeString());
    }
}
