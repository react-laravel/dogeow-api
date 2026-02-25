<?php

namespace Database\Factories\Note;

use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note\NoteTag>
 */
class NoteTagFactory extends Factory
{
    /** @var class-string<\App\Models\Note\NoteTag> */
    protected $model = NoteTag::class;

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
            'color' => $this->faker->hexColor(),
        ];
    }
}
