<?php

namespace Database\Factories\Nav;

use App\Models\Nav\Category;
use App\Models\Nav\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nav\Item>
 */
class ItemFactory extends Factory
{
    /** @var class-string<\App\Models\Nav\Item> */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nav_category_id' => Category::factory(),
            'name' => $this->faker->word(),
            'url' => $this->faker->url(),
            'icon' => $this->faker->randomElement(['home', 'settings', 'user', 'tools', 'star', 'dashboard']),
            'description' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_visible' => $this->faker->boolean(80), // 80% chance of being visible
            'is_new_window' => $this->faker->boolean(20), // 20% chance of opening in new window
            'clicks' => $this->faker->numberBetween(0, 1000),
        ];
    }

    /**
     * Indicate that the item is visible.
     */
    public function visible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => true,
        ]);
    }

    /**
     * Indicate that the item is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }

    /**
     * Indicate that the item opens in new window.
     */
    public function newWindow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_new_window' => true,
        ]);
    }
}
