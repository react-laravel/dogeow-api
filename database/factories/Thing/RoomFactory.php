<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thing\Room>
 */
class RoomFactory extends Factory
{
    /** @var class-string<\App\Models\Thing\Room> */
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'area_id' => Area::factory(),
            'user_id' => User::factory(),
        ];
    }
}
