<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Word\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Word\Book>
 */
class WordBookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'word_count' => fake()->numberBetween(10, 100),
            'is_public' => true,
            'created_by' => User::factory(),
        ];
    }
}
