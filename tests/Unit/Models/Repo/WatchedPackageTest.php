<?php

namespace Tests\Unit\Models\Repo;

use App\Models\Repo\WatchedPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WatchedPackageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function belongs_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $package = WatchedPackage::create([
            'user_id' => $user->id,
            'source_provider' => 'github',
            'source_owner' => 'test-owner',
            'source_repo' => 'test-repo',
            'source_url' => 'https://github.com/test-owner/test-repo',
            'ecosystem' => 'npm',
            'package_name' => 'test-package',
            'manifest_path' => 'package.json',
            'current_version_constraint' => '^1.0.0',
            'normalized_current_version' => '1.0.0',
            'latest_version' => '1.1.0',
            'watch_level' => 'major',
            'latest_update_type' => 'minor',
            'registry_url' => 'https://registry.npmjs.org',
            'last_checked_at' => now(),
            'last_error' => null,
            'metadata' => [],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $package->user);
        $this->assertEquals($user->id, $package->user->id);
    }

    #[Test]
    public function metadata_is_cast_to_array(): void
    {
        // Arrange
        $user = User::factory()->create();
        $metadata = ['keywords' => ['test', 'package'], 'description' => 'A test package'];

        // Act
        $package = WatchedPackage::create([
            'user_id' => $user->id,
            'source_provider' => 'github',
            'source_owner' => 'test-owner',
            'source_repo' => 'test-repo',
            'source_url' => 'https://github.com/test-owner/test-repo',
            'ecosystem' => 'npm',
            'package_name' => 'test-package',
            'manifest_path' => 'package.json',
            'current_version_constraint' => '^1.0.0',
            'normalized_current_version' => '1.0.0',
            'latest_version' => '1.1.0',
            'watch_level' => 'major',
            'latest_update_type' => 'minor',
            'registry_url' => 'https://registry.npmjs.org',
            'last_checked_at' => now(),
            'last_error' => null,
            'metadata' => $metadata,
        ]);

        // Assert
        $package->refresh();
        $this->assertIsArray($package->metadata);
        $this->assertEquals(['test', 'package'], $package->metadata['keywords']);
    }

    #[Test]
    public function last_checked_at_is_cast_to_datetime(): void
    {
        // Arrange
        $user = User::factory()->create();
        $checkedAt = now();

        // Act
        $package = WatchedPackage::create([
            'user_id' => $user->id,
            'source_provider' => 'github',
            'source_owner' => 'test-owner',
            'source_repo' => 'test-repo',
            'source_url' => 'https://github.com/test-owner/test-repo',
            'ecosystem' => 'npm',
            'package_name' => 'test-package',
            'manifest_path' => 'package.json',
            'current_version_constraint' => '^1.0.0',
            'normalized_current_version' => '1.0.0',
            'latest_version' => '1.1.0',
            'watch_level' => 'major',
            'latest_update_type' => 'minor',
            'registry_url' => 'https://registry.npmjs.org',
            'last_checked_at' => $checkedAt,
            'last_error' => null,
            'metadata' => [],
        ]);

        // Assert
        $package->refresh();
        $this->assertInstanceOf(\Carbon\Carbon::class, $package->last_checked_at);
        $this->assertEquals($checkedAt->toDateTimeString(), $package->last_checked_at->toDateTimeString());
    }

    #[Test]
    public function fillable_contains_expected_fields(): void
    {
        // Arrange
        $package = new WatchedPackage;

        // Assert
        $this->assertEquals([
            'user_id',
            'source_provider',
            'source_owner',
            'source_repo',
            'source_url',
            'ecosystem',
            'package_name',
            'manifest_path',
            'current_version_constraint',
            'normalized_current_version',
            'latest_version',
            'watch_level',
            'latest_update_type',
            'registry_url',
            'last_checked_at',
            'last_error',
            'metadata',
        ], $package->getFillable());
    }
}
