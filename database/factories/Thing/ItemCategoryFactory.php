<?php

namespace Database\Factories\Thing;

use App\Models\Thing\ItemCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * 创建子分类
     */
    public function child(?ItemCategory $parent = null)
    {
        return $this->state(function (array $attributes) use ($parent) {
            return [
                'parent_id' => $parent ? $parent->id : ItemCategory::factory()->create()->id,
            ];
        });
    }

    /**
     * 创建主分类
     */
    public function parent()
    {
        return $this->state(function (array $attributes) {
            return [
                'parent_id' => null,
            ];
        });
    }
}
