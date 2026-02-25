<?php

namespace Database\Factories\Thing;

use App\Models\Thing\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /** @var class-string<\App\Models\Thing\ItemCategory> */
    protected $model = ItemCategory::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
