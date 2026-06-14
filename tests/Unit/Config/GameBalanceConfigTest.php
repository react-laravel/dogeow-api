<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class GameBalanceConfigTest extends TestCase
{
    public function test_rpg_balance_uses_low_number_curve(): void
    {
        $this->assertSame(780000, config('game.experience_table.46'));
        $this->assertSame(3, config('game.hp.vitality_multiplier'));
        $this->assertSame('strength', config('game.combat.attack.stat'));
        $this->assertSame(1, config('game.combat.attack.multiplier'));
        $this->assertSame(1.8, config('game.monster_type_multipliers.elite'));
        $this->assertSame(3, config('game.monster_type_multipliers.boss'));
        $this->assertSame([5, 15], config('game.shop.gem_stat_ranges.max_hp'));
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

        $this->assertSame([2, 3, 1], array_column($secondLayerMonsters, 'attack_base'));
        $this->assertSame([9, 6, 3], array_column($secondLayerMonsters, 'hp_base'));
        $this->assertSame([2, 4, 6], array_column($secondLayerMonsters, 'defense_base'));
    }
}
