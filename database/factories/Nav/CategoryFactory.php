<?php

namespace Database\Factories\Nav;

use App\Models\Nav\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nav\Category>
 */
class CategoryFactory extends Factory
{
    /** @var class-string<\App\Models\Nav\Category> */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'icon' => $this->faker->randomElement(['home', 'settings', 'user', 'tools', 'star']),
            'description' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_visible' => $this->faker->boolean(80), // 80% chance of being visible
        ];
    }

    /**
     * Indicate that the category is visible.
     */
    public function visible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => true,
        ]);
    }

    /**
     * Indicate that the category is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }
}
