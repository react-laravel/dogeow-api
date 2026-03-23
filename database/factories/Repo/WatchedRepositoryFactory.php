<?php

namespace Database\Factories\Repo;

use App\Models\Repo\WatchedRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchedRepository>
 */
class WatchedRepositoryFactory extends Factory
{
    protected $model = WatchedRepository::class;

    public function definition(): array
    {
        $owner = $this->faker->userName;
        $repo = $this->faker->slug;

        return [
            'user_id' => User::factory(),
            'provider' => 'github',
            'owner' => $owner,
            'repo' => $repo,
            'full_name' => "{$owner}/{$repo}",
            'html_url' => "https://github.com/{$owner}/{$repo}",
            'default_branch' => 'main',
            'language' => $this->faker->randomElement(['PHP', 'JavaScript', 'Python', 'Ruby']),
            'ecosystem' => null,
            'package_name' => null,
            'manifest_path' => null,
            'latest_version' => $this->faker->semver(),
            'latest_source_type' => 'release',
            'latest_release_url' => "https://github.com/{$owner}/{$repo}/releases/tag/v1.0.0",
            'description' => $this->faker->sentence(),
            'latest_release_published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'last_checked_at' => now(),
            'last_error' => null,
            'metadata' => ['stars' => $this->faker->numberBetween(0, 1000)],
        ];
    }
}