<?php

namespace Database\Factories\Game;

use App\Models\Game\GameSkillDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSkillDefinition>
 */
class GameSkillDefinitionFactory extends Factory
{
    protected $model = GameSkillDefinition::class;

    public function definition(): array
    {
        $effectKey = fake()->unique()->slug(2);
        $type = fake()->randomElement(GameSkillDefinition::TYPES);
        $manaCost = $type === 'active' ? fake()->numberBetween(5, 40) : 0;
        $cooldown = $type === 'active' ? fake()->numberBetween(0, 12) : 0;
        $baseDamage = $type === 'active' ? fake()->numberBetween(10, 200) : 0;
        $damagePerLevel = $type === 'active' ? fake()->numberBetween(1, 15) : 0;
        $manaCostPerLevel = $type === 'active' ? fake()->numberBetween(0, 3) : 0;

        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'type' => $type,
            'class_restriction' => fake()->randomElement(GameSkillDefinition::CLASS_RESTRICTIONS),
            'mana_cost' => $manaCost,
            'cooldown' => $cooldown,
            'skill_points_cost' => fake()->numberBetween(1, 3),
            'max_level' => 10,
            'base_damage' => $baseDamage,
            'damage_per_level' => $damagePerLevel,
            'mana_cost_per_level' => $manaCostPerLevel,
            'icon' => $effectKey . '.png',
            'effect_key' => $effectKey,
            'effects' => [],
            'target_type' => fake()->randomElement(['single', 'all']),
            'is_active' => true,
            'branch' => fake()->randomElement(['warrior', 'fire', 'ice', 'lightning', 'arcane', 'ranger', 'poison', 'passive', 'healing']),
            'tier' => fake()->numberBetween(1, 3),
            'prerequisite_skill_id' => null,
            'prerequisite_effect_key' => null,
        ];
    }
}
