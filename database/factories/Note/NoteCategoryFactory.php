<?php

namespace Database\Factories\Note;

use App\Models\Note\NoteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note\NoteCategory>
 */
class NoteCategoryFactory extends Factory
{
    /** @var class-string<\App\Models\Note\NoteCategory> */
    protected $model = NoteCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
