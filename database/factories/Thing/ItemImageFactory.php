<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thing\ItemImage>
 */
class ItemImageFactory extends Factory
{
    /** @var class-string<\App\Models\Thing\ItemImage> */
    protected $model = ItemImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = $this->faker->randomElement($imageExtensions);
        $filename = $this->faker->uuid() . '.' . $extension;

        return [
            'item_id' => Item::factory(),
            'path' => 'items/' . $this->faker->numberBetween(1, 100) . '/' . $filename,
            'is_primary' => false,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the image is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * Indicate that the image has a specific sort order.
     */
    public function sortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
