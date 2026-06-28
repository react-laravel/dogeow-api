<?php

namespace Database\Factories\Note;

use App\Models\Note\NoteTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteTag>
 */
class NoteTagFactory extends Factory
{
    /** @var class-string<NoteTag> */
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
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
