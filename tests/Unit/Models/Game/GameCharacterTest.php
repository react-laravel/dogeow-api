<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameCharacter;
use Tests\TestCase;

class GameCharacterTest extends TestCase
{
    protected GameCharacter $character;

    protected function setUp(): void
    {
        parent::setUp();
        $this->character = new GameCharacter;
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->character->getFillable();
        $this->assertContains('user_id', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('class', $fillable);
        $this->assertContains('gender', $fillable);
        $this->assertContains('level', $fillable);
        $this->assertContains('experience', $fillable);
        $this->assertContains('copper', $fillable);
        $this->assertContains('strength', $fillable);
        $this->assertContains('dexterity', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->character->getCasts();
        $this->assertArrayHasKey('is_fighting', $casts);
        $this->assertArrayHasKey('last_combat_at', $casts);
        $this->assertArrayHasKey('auto_use_hp_potion', $casts);
        $this->assertArrayHasKey('auto_use_mp_potion', $casts);
    }

    public function test_get_slots_returns_default_slots(): void
    {
        $slots = GameCharacter::getSlots();
        $this->assertIsArray($slots);
        $this->assertContains('weapon', $slots);
    }

    public function test_has_active_combat_returns_false_when_no_monsters(): void
    {
        $character = new GameCharacter(['combat_monsters' => null]);
        $this->assertFalse($character->hasActiveCombat());
    }

    public function test_has_active_combat_returns_false_when_all_monsters_dead(): void
    {
        $character = new GameCharacter([
            'combat_monsters' => [
                ['hp' => 0, 'id' => 1],
                ['hp' => 0, 'id' => 2],
            ],
        ]);
        $this->assertFalse($character->hasActiveCombat());
    }

    public function test_has_active_combat_returns_true_when_monster_alive(): void
    {
        $character = new GameCharacter([
            'combat_monsters' => [
                ['hp' => 100, 'id' => 1],
                ['hp' => 0, 'id' => 2],
            ],
        ]);
        $this->assertTrue($character->hasActiveCombat());
    }

    public function test_clear_combat_state_resets_combat_fields(): void
    {
        $character = new GameCharacter([
            'combat_monster_id' => 1,
            'combat_monster_hp' => 100,
            'combat_total_damage_dealt' => 500,
            'combat_rounds' => 10,
        ]);
        $character->clearCombatState();

        $this->assertNull($character->combat_monster_id);
        $this->assertEquals(0, $character->combat_total_damage_dealt);
    }

    public function test_user_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->character, 'user'));
    }

    public function test_equipment_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->character, 'equipment'));
    }

    public function test_items_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->character, 'items'));
    }

    public function test_skills_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->character, 'skills'));
    }
}
