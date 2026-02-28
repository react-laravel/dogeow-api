<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameMonsterDefinition;
use Tests\TestCase;

class GameMonsterDefinitionTest extends TestCase
{
    protected GameMonsterDefinition $monster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monster = new GameMonsterDefinition();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->monster->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('level', $fillable);
        $this->assertContains('hp_base', $fillable);
        $this->assertContains('attack_base', $fillable);
        $this->assertContains('defense_base', $fillable);
        $this->assertContains('experience_base', $fillable);
        $this->assertContains('drop_table', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->monster->getCasts();
        $this->assertArrayHasKey('drop_table', $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertEquals('array', $casts['drop_table']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_model_has_correct_constants(): void
    {
        $this->assertEquals(['normal', 'elite', 'boss'], GameMonsterDefinition::TYPES);
    }

    public function test_get_hp_returns_integer(): void
    {
        $monster = new GameMonsterDefinition(['hp_base' => 100]);
        $this->assertEquals(100, $monster->getHp());
    }

    public function test_get_hp_returns_zero_when_null(): void
    {
        $monster = new GameMonsterDefinition(['hp_base' => null]);
        $this->assertEquals(0, $monster->getHp());
    }

    public function test_get_attack_returns_integer(): void
    {
        $monster = new GameMonsterDefinition(['attack_base' => 50]);
        $this->assertEquals(50, $monster->getAttack());
    }

    public function test_get_defense_returns_integer(): void
    {
        $monster = new GameMonsterDefinition(['defense_base' => 30]);
        $this->assertEquals(30, $monster->getDefense());
    }

    public function test_get_experience_returns_integer(): void
    {
        $monster = new GameMonsterDefinition(['experience_base' => 200]);
        $this->assertEquals(200, $monster->getExperience());
    }

    public function test_get_combat_stats_returns_array(): void
    {
        $monster = new GameMonsterDefinition([
            'hp_base' => 100,
            'attack_base' => 50,
            'defense_base' => 30,
            'experience_base' => 200,
        ]);
        $stats = $monster->getCombatStats();
        $this->assertIsArray($stats);
        $this->assertEquals(100, $stats['hp']);
        $this->assertEquals(50, $stats['attack']);
        $this->assertEquals(30, $stats['defense']);
        $this->assertEquals(200, $stats['experience']);
    }

    public function test_generate_loot_with_empty_drop_table(): void
    {
        $monster = new GameMonsterDefinition([
            'level' => 10,
            'drop_table' => [],
        ]);
        $loot = $monster->generateLoot(10);
        $this->assertIsArray($loot);
        $this->assertEmpty($loot);
    }

    public function test_generate_loot_with_potion_chance(): void
    {
        $monster = new GameMonsterDefinition([
            'level' => 10,
            'drop_table' => [
                'potion_chance' => 1.0,
                'item_chance' => 0,
            ],
        ]);
        $loot = $monster->generateLoot(10);
        $this->assertArrayHasKey('potion', $loot);
        $this->assertEquals('potion', $loot['potion']['type']);
    }

    public function test_generate_loot_with_item_chance(): void
    {
        $monster = new GameMonsterDefinition([
            'level' => 10,
            'drop_table' => [
                'potion_chance' => 0,
                'item_chance' => 1.0,
                'item_types' => ['weapon'],
            ],
        ]);
        $loot = $monster->generateLoot(10);
        $this->assertArrayHasKey('item', $loot);
        $this->assertEquals('weapon', $loot['item']['type']);
    }

    public function test_generate_loot_potion_level_based_on_monster_level(): void
    {
        $monster = new GameMonsterDefinition([
            'level' => 5,
            'drop_table' => [
                'potion_chance' => 1.0,
                'item_chance' => 0,
            ],
        ]);
        $loot = $monster->generateLoot(5);
        $this->assertEquals('minor', $loot['potion']['level']);

        $monster = new GameMonsterDefinition([
            'level' => 20,
            'drop_table' => [
                'potion_chance' => 1.0,
                'item_chance' => 0,
            ],
        ]);
        $loot = $monster->generateLoot(20);
        $this->assertEquals('light', $loot['potion']['level']);

        $monster = new GameMonsterDefinition([
            'level' => 45,
            'drop_table' => [
                'potion_chance' => 1.0,
                'item_chance' => 0,
            ],
        ]);
        $loot = $monster->generateLoot(45);
        $this->assertEquals('medium', $loot['potion']['level']);

        $monster = new GameMonsterDefinition([
            'level' => 70,
            'drop_table' => [
                'potion_chance' => 1.0,
                'item_chance' => 0,
            ],
        ]);
        $loot = $monster->generateLoot(70);
        $this->assertEquals('full', $loot['potion']['level']);
    }

    public function test_generate_item_quality_uses_config(): void
    {
        $monster = new GameMonsterDefinition(['level' => 10]);
        $reflection = new \ReflectionClass($monster);
        $method = $reflection->getMethod('generateItemQuality');
        $method->setAccessible(true);

        $qualities = [];
        for ($i = 0; $i < 100; $i++) {
            $quality = $method->invoke($monster);
            $qualities[] = $quality;
            $this->assertContains($quality, ['common', 'magic', 'rare', 'legendary', 'mythic']);
        }
        $this->assertGreaterThan(0, count($qualities));
    }

    public function test_combat_logs_relationship(): void
    {
        $monster = new GameMonsterDefinition();
        $this->assertTrue(method_exists($monster, 'combatLogs'));
    }
}
