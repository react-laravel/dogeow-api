<?php

namespace Database\Factories\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note\Note>
 */
class NoteFactory extends Factory
{
    /** @var class-string<\App\Models\Note\Note> */
    protected $model = Note::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'note_category_id' => NoteCategory::factory(),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'content_markdown' => $this->faker->paragraphs(3, true),
            'is_draft' => $this->faker->boolean(20), // 20% chance of being draft
        ];
    }

    /**
     * Indicate that the note is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => true,
        ]);
    }

    /**
     * Indicate that the note is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => false,
        ]);
    }

    /**
     * Indicate that the note has markdown content.
     */
    public function withMarkdown(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_markdown' => '# ' . $this->faker->sentence() . "\n\n" . $this->faker->paragraphs(2, true),
        ]);
    }
}
