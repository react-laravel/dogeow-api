<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thing\Tag>
 */
class TagFactory extends Factory
{
    /** @var class-string<\App\Models\Thing\Tag> */
    protected $model = Tag::class;

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
