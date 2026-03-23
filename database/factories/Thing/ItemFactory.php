<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thing\Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition()
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'user_id' => User::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'status' => $this->faker->randomElement(['active', 'inactive', 'expired']),
            'expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+2 years'),
            'purchase_date' => $this->faker->optional()->dateTimeBetween('-2 years', 'now'),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 0, 10000),
            'category_id' => null,
            'area_id' => null,
            'room_id' => null,
            'spot_id' => null,
            'is_public' => $this->faker->boolean(80),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * 设置物品为活跃状态
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
            ];
        });
    }

    /**
     * 设置物品为非活跃状态
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
            ];
        });
    }

    /**
     * 设置物品为已过期状态
     */
    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'expired',
            ];
        });
    }

    /**
     * 设置物品为公开
     */
    public function public()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_public' => true,
            ];
        });
    }

    /**
     * 设置物品为私有
     */
    public function private()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_public' => false,
            ];
        });
    }

    /**
     * 设置物品有分类
     */
    public function withCategory(?ItemCategory $category = null)
    {
        return $this->state(function (array $attributes) use ($category) {
            return [
                'category_id' => $category ? $category->id : ItemCategory::factory()->create()->id,
            ];
        });
    }

    /**
     * 设置物品有过期日期
     */
    public function withExpiryDate()
    {
        return $this->state(function (array $attributes) {
            return [
                'expiry_date' => $this->faker->dateTimeBetween('now', '+2 years'),
            ];
        });
    }

    /**
     * 设置物品有购买信息
     */
    public function withPurchaseInfo()
    {
        return $this->state(function (array $attributes) {
            return [
                'purchase_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
                'purchase_price' => $this->faker->randomFloat(2, 0, 10000),
            ];
        });
    }
}
