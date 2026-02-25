<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thing\Area>
 */
class AreaFactory extends Factory
{
    /** @var class-string<\App\Models\Thing\Area> */
    protected $model = Area::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'user_id' => User::factory(),
        ];
    }
}
