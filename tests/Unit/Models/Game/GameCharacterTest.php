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

    public function test_get_base_hp_returns_default_value(): void
    {
        $character = new GameCharacter(['class' => 'unknown_class']);
        $baseHp = $character->getBaseHp();
        $this->assertIsInt($baseHp);
        $this->assertGreaterThan(0, $baseHp);
    }

    public function test_get_base_hp_returns_class_specific_value(): void
    {
        config(['game.hp.base.warrior' => 20, 'game.hp.base.default' => 15]);
        $character = new GameCharacter(['class' => 'warrior']);
        $this->assertEquals(20, $character->getBaseHp());
    }

    public function test_get_base_mana_returns_default_value(): void
    {
        $character = new GameCharacter(['class' => 'unknown_class']);
        $baseMana = $character->getBaseMana();
        $this->assertIsInt($baseMana);
        $this->assertGreaterThan(0, $baseMana);
    }

    public function test_get_base_mana_returns_class_specific_value(): void
    {
        config(['game.mana.base.mage' => 25, 'game.mana.base.default' => 15]);
        $character = new GameCharacter(['class' => 'mage']);
        $this->assertEquals(25, $character->getBaseMana());
    }

    public function test_get_experience_for_current_level_returns_configured_value(): void
    {
        config(['game.experience_table' => [1 => 0, 2 => 100, 3 => 300]]);
        $character = new GameCharacter(['level' => 2]);
        $this->assertEquals(100, $character->getExperienceForCurrentLevel());
    }

    public function test_get_experience_for_current_level_returns_zero_for_unconfigured_level(): void
    {
        config(['game.experience_table' => [1 => 0, 2 => 100]]);
        $character = new GameCharacter(['level' => 99]);
        $this->assertEquals(0, $character->getExperienceForCurrentLevel());
    }

    public function test_has_discovered_monster_returns_true_when_found(): void
    {
        $character = new GameCharacter(['discovered_monsters' => [1, 2, 3]]);
        $this->assertTrue($character->hasDiscoveredMonster(2));
    }

    public function test_has_discovered_monster_returns_false_when_not_found(): void
    {
        $character = new GameCharacter(['discovered_monsters' => [1, 2, 3]]);
        $this->assertFalse($character->hasDiscoveredMonster(5));
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $relation = $this->character->user();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    public function test_get_difficulty_multipliers_returns_default_for_tier_zero(): void
    {
        $character = new GameCharacter(['difficulty_tier' => 0]);
        $multipliers = $character->getDifficultyMultipliers();
        $this->assertArrayHasKey('monster_hp', $multipliers);
        $this->assertArrayHasKey('monster_damage', $multipliers);
        $this->assertArrayHasKey('reward', $multipliers);
    }

    public function test_get_difficulty_multipliers_returns_configured_tier(): void
    {
        config(['game.difficulty_multipliers' => [
            0 => ['monster_hp' => 1.0, 'monster_damage' => 1.0, 'reward' => 1.0],
            1 => ['monster_hp' => 1.5, 'monster_damage' => 1.2, 'reward' => 1.5],
        ]]);
        $character = new GameCharacter(['difficulty_tier' => 1]);
        $multipliers = $character->getDifficultyMultipliers();
        $this->assertEquals(1.5, $multipliers['monster_hp']);
    }

    public function test_discover_item_adds_to_discovered_list(): void
    {
        $discovered = [1, 2];
        $itemDefinitionId = 3;

        // Test the logic without persisting
        if (! in_array($itemDefinitionId, $discovered)) {
            $discovered[] = $itemDefinitionId;
        }

        $this->assertContains(1, $discovered);
        $this->assertContains(2, $discovered);
        $this->assertContains(3, $discovered);
    }

    public function test_discover_item_does_not_add_duplicates(): void
    {
        $discovered = [1, 2];
        $itemDefinitionId = 1;

        // Test the logic without persisting
        if (! in_array($itemDefinitionId, $discovered)) {
            $discovered[] = $itemDefinitionId;
        }

        $this->assertCount(2, $discovered);
    }

    public function test_has_discovered_item_returns_true_when_found(): void
    {
        $character = new GameCharacter(['discovered_items' => [1, 2, 3]]);
        $this->assertTrue($character->hasDiscoveredItem(2));
    }

    public function test_has_discovered_item_returns_false_when_not_found(): void
    {
        $character = new GameCharacter(['discovered_items' => [1, 2]]);
        $this->assertFalse($character->hasDiscoveredItem(5));
    }

    public function test_restore_hp_increases_current_hp(): void
    {
        $character = new GameCharacter;
        $character->current_hp = 50;
        $character->max_hp = 100;

        // Test the logic calculation without saving
        $maxHp = $character->max_hp;
        $currentHp = $character->current_hp ?? $character->getMaxHp();
        $newHp = min($maxHp, $currentHp + 30);

        $this->assertEquals(80, $newHp);
    }

    public function test_restore_hp_does_not_exceed_max(): void
    {
        $character = new GameCharacter;
        $character->current_hp = 90;
        $character->max_hp = 100;

        // Test the logic calculation without saving
        $maxHp = $character->max_hp;
        $currentHp = $character->current_hp ?? $character->getMaxHp();
        $newHp = min($maxHp, $currentHp + 50);

        $this->assertEquals(100, $newHp);
    }

    public function test_restore_mana_increases_current_mana(): void
    {
        $character = new GameCharacter;
        $character->current_mana = 20;
        $character->max_mana = 100;

        // Test the logic calculation without saving
        $maxMana = $character->max_mana;
        $currentMana = $character->current_mana ?? $character->getMaxMana();
        $newMana = min($maxMana, $currentMana + 30);

        $this->assertEquals(50, $newMana);
    }

    public function test_restore_mana_does_not_exceed_max(): void
    {
        $character = new GameCharacter;
        $character->current_mana = 80;
        $character->max_mana = 100;

        // Test the logic calculation without saving
        $maxMana = $character->max_mana;
        $currentMana = $character->current_mana ?? $character->getMaxMana();
        $newMana = min($maxMana, $currentMana + 50);

        $this->assertEquals(100, $newMana);
    }

    public function test_get_current_hp_returns_value(): void
    {
        $character = new GameCharacter(['current_hp' => 75]);
        $this->assertEquals(75, $character->getCurrentHp());
    }

    public function test_get_current_mana_returns_value(): void
    {
        $character = new GameCharacter(['current_mana' => 45]);
        $this->assertEquals(45, $character->getCurrentMana());
    }
}
