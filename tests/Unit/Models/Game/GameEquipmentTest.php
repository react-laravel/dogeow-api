<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameEquipment;
use Tests\TestCase;

class GameEquipmentTest extends TestCase
{
    protected GameEquipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->equipment = new GameEquipment();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->equipment->getFillable();
        $this->assertContains('character_id', $fillable);
        $this->assertContains('slot', $fillable);
        $this->assertContains('item_id', $fillable);
    }

    public function test_get_slots_returns_default_slots(): void
    {
        $slots = GameEquipment::getSlots();
        $this->assertIsArray($slots);
        $this->assertContains('weapon', $slots);
    }

    public function test_character_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->equipment, 'character'));
    }

    public function test_item_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->equipment, 'item'));
    }
}
