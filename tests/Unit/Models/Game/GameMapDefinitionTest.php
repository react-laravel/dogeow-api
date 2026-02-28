<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameMapDefinition;
use Tests\TestCase;

class GameMapDefinitionTest extends TestCase
{
    protected GameMapDefinition $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->map = new GameMapDefinition;
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->map->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('act', $fillable);
        $this->assertContains('monster_ids', $fillable);
        $this->assertContains('background', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('is_active', $fillable);
    }

    public function test_model_uses_correct_hidden_attributes(): void
    {
        $hidden = $this->map->getHidden();
        $this->assertContains('min_level', $hidden);
        $this->assertContains('max_level', $hidden);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->map->getCasts();
        $this->assertArrayHasKey('monster_ids', $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertEquals('array', $casts['monster_ids']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_can_enter_always_returns_true(): void
    {
        $map = new GameMapDefinition;
        $this->assertTrue($map->canEnter(1));
        $this->assertTrue($map->canEnter(50));
        $this->assertTrue($map->canEnter(100));
    }

    public function test_get_level_range_text_returns_no_restriction(): void
    {
        $map = new GameMapDefinition;
        $this->assertEquals('No Level Restriction', $map->getLevelRangeText());
    }

    public function test_character_maps_relationship(): void
    {
        $map = new GameMapDefinition;
        $this->assertTrue(method_exists($map, 'characterMaps'));
    }

    public function test_get_monsters_returns_empty_array_when_no_monster_ids(): void
    {
        $map = new GameMapDefinition(['monster_ids' => null]);
        $monsters = $map->getMonsters();
        $this->assertIsArray($monsters);
        $this->assertEmpty($monsters);
    }

    public function test_get_monsters_returns_empty_array_when_monster_ids_empty(): void
    {
        $map = new GameMapDefinition(['monster_ids' => []]);
        $monsters = $map->getMonsters();
        $this->assertIsArray($monsters);
        $this->assertEmpty($monsters);
    }

    public function test_get_monsters_filters_invalid_ids(): void
    {
        $map = new GameMapDefinition(['monster_ids' => [0, -1, null, 'abc']]);
        $monsters = $map->getMonsters();
        $this->assertIsArray($monsters);
        $this->assertEmpty($monsters);
    }
}
