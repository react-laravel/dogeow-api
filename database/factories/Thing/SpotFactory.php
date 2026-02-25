<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thing\Spot>
 */
class SpotFactory extends Factory
{
    /** @var class-string<\App\Models\Thing\Spot> */
    protected $model = Spot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
        ];
    }
}
