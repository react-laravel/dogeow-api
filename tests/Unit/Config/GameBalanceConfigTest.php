<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class GameBalanceConfigTest extends TestCase
{
    public function test_rpg_balance_uses_low_number_curve(): void
    {
        $this->assertSame(50, config('game.experience_table.2'));
        $this->assertSame(250, config('game.experience_table.3'));
        $this->assertSame(1569750, config('game.experience_table.46'));
        $this->assertSame(3, config('game.hp.vitality_multiplier'));
        $this->assertSame('strength', config('game.combat.attack.stat'));
        $this->assertSame(1, config('game.combat.attack.multiplier'));
        $this->assertSame(1.8, config('game.monster_type_multipliers.elite'));
        $this->assertSame(3, config('game.monster_type_multipliers.boss'));
        $this->assertSame(10, config('game.test_mode.copper_drop_chance_multiplier'));
        $this->assertSame(10, config('game.test_mode.potion_drop_chance_multiplier'));
        $this->assertSame(10, config('game.test_mode.equipment_drop_chance_multiplier'));
    }

    public function test_newbie_camp_monsters_are_harmless(): void
    {
        $monsters = require base_path('database/seeders/Game/Data/monsters.php');
        $newbieMonsters = array_slice($monsters, 0, 3);
        $secondLayerMonsters = array_slice($monsters, 3, 3);

        $this->assertSame(['猪', '鹿', '兔子'], array_column($newbieMonsters, 'name'));
        $this->assertSame([0, 0, 0], array_column($newbieMonsters, 'attack_base'));
        $this->assertSame([3, 2, 1], array_column($newbieMonsters, 'hp_base'));
        $this->assertSame([1, 2, 3], array_column($newbieMonsters, 'defense_base'));
        $this->assertSame([3, 2, 2], array_column($newbieMonsters, 'experience_base'));

        $this->assertSame([2, 3, 1], array_column($secondLayerMonsters, 'attack_base'));
        $this->assertSame([9, 6, 3], array_column($secondLayerMonsters, 'hp_base'));
        $this->assertSame([2, 4, 6], array_column($secondLayerMonsters, 'defense_base'));
        $this->assertSame([4, 4, 4], array_column($secondLayerMonsters, 'experience_base'));
    }

    public function test_early_elite_and_boss_hp_stays_on_small_number_curve(): void
    {
        $monsters = require base_path('database/seeders/Game/Data/monsters.php');
        $thirdLayerMonsters = array_slice($monsters, 6, 3);

        $this->assertSame(['elite', 'boss', 'elite'], array_column($thirdLayerMonsters, 'type'));
        $this->assertSame([52, 140, 70], array_column($thirdLayerMonsters, 'hp_base'));
    }

    public function test_mage_starter_fireball_uses_low_fixed_damage(): void
    {
        $skills = require base_path('database/seeders/Game/Data/Skills/skills_mage.php');
        $fireball = collect($skills)->first(
            fn (array $skill): bool => ($skill['skill_line'] ?? null) === 'mage_fireball'
                && (int) ($skill['node_tier'] ?? -1) === 0
        );

        $this->assertSame('小火球', $fireball['name'] ?? null);
        $this->assertSame(2, $fireball['base_damage'] ?? null);
        $this->assertSame(8, $fireball['mana_cost'] ?? null);
    }
}
