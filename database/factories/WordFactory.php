<?php

namespace Database\Factories;

use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Word\Word>
 */
class WordFactory extends Factory
{
    protected $model = Word::class;

    public function definition(): array
    {
        return [
            'word' => fake()->word(),
            'definition' => fake()->sentence(),
            'phonetic' => '/' . fake()->lexify('????') . '/',
            'translation' => fake()->words(2, true),
            'example' => fake()->sentence(),
            'audio_url' => null,
            'book_id' => null,
            'level' => fake()->numberBetween(1, 10),
        ];
    }
}
