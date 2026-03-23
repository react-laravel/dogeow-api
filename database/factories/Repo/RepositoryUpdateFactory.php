<?php

namespace Database\Factories\Repo;

use App\Models\Repo\RepositoryUpdate;
use App\Models\Repo\WatchedRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryUpdate>
 */
class RepositoryUpdateFactory extends Factory
{
    protected $model = RepositoryUpdate::class;

    public function definition(): array
    {
        return [
            'watched_repository_id' => WatchedRepository::factory(),
            'source_type' => 'release',
            'source_id' => (string) $this->faker->randomNumber(5),
            'version' => $this->faker->semver(),
            'title' => $this->faker->sentence(4),
            'release_url' => $this->faker->url(),
            'body' => $this->faker->paragraphs(3, true),
            'ai_summary' => null,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'metadata' => ['prerelease' => false, 'draft' => false],
        ];
    }
}