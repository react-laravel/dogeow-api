<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameItemDefinition;
use Tests\TestCase;

class GameItemDefinitionTest extends TestCase
{
    protected GameItemDefinition $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->item = new GameItemDefinition();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->item->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('sub_type', $fillable);
        $this->assertContains('base_stats', $fillable);
        $this->assertContains('required_level', $fillable);
        $this->assertContains('icon', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('sockets', $fillable);
        $this->assertContains('gem_stats', $fillable);
        $this->assertContains('buy_price', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->item->getCasts();
        $this->assertArrayHasKey('base_stats', $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertArrayHasKey('gem_stats', $casts);
        $this->assertEquals('array', $casts['base_stats']);
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('array', $casts['gem_stats']);
    }

    public function test_model_has_correct_type_constants(): void
    {
        $expectedTypes = [
            'weapon',
            'helmet',
            'armor',
            'gloves',
            'boots',
            'belt',
            'ring',
            'amulet',
            'potion',
            'gem',
        ];
        $this->assertEquals($expectedTypes, GameItemDefinition::TYPES);
    }

    public function test_model_has_correct_sub_type_constants(): void
    {
        $expectedSubTypes = [
            'sword',
            'axe',
            'mace',
            'staff',
            'bow',
            'dagger',
            'cloth',
            'leather',
            'mail',
            'plate',
        ];
        $this->assertEquals($expectedSubTypes, GameItemDefinition::SUB_TYPES);
    }

    public function test_get_equipment_slot_returns_correct_slot_for_weapon(): void
    {
        $item = new GameItemDefinition(['type' => 'weapon']);
        $this->assertEquals('weapon', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_helmet(): void
    {
        $item = new GameItemDefinition(['type' => 'helmet']);
        $this->assertEquals('helmet', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_armor(): void
    {
        $item = new GameItemDefinition(['type' => 'armor']);
        $this->assertEquals('armor', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_gloves(): void
    {
        $item = new GameItemDefinition(['type' => 'gloves']);
        $this->assertEquals('gloves', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_boots(): void
    {
        $item = new GameItemDefinition(['type' => 'boots']);
        $this->assertEquals('boots', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_belt(): void
    {
        $item = new GameItemDefinition(['type' => 'belt']);
        $this->assertEquals('belt', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_ring(): void
    {
        $item = new GameItemDefinition(['type' => 'ring']);
        $this->assertEquals('ring', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_correct_slot_for_amulet(): void
    {
        $item = new GameItemDefinition(['type' => 'amulet']);
        $this->assertEquals('amulet', $item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_null_for_potion(): void
    {
        $item = new GameItemDefinition(['type' => 'potion']);
        $this->assertNull($item->getEquipmentSlot());
    }

    public function test_get_equipment_slot_returns_null_for_gem(): void
    {
        $item = new GameItemDefinition(['type' => 'gem']);
        $this->assertNull($item->getEquipmentSlot());
    }

    public function test_get_base_stats_returns_array(): void
    {
        $item = new GameItemDefinition(['base_stats' => ['attack' => 10, 'defense' => 5]]);
        $stats = $item->getBaseStats();
        $this->assertIsArray($stats);
        $this->assertEquals(10, $stats['attack']);
        $this->assertEquals(5, $stats['defense']);
    }

    public function test_get_base_stats_returns_empty_array_when_null(): void
    {
        $item = new GameItemDefinition(['base_stats' => null]);
        $stats = $item->getBaseStats();
        $this->assertIsArray($stats);
        $this->assertEmpty($stats);
    }
}
