<?php

namespace Tests\Unit\Services\Game\Combat;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameCharacterSkill;
use App\Models\Game\GameSkillDefinition;
use App\Models\User;
use App\Services\Game\Combat\CombatSkillSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CombatSkillSelectorTest extends TestCase
{
    use RefreshDatabase;

    private CombatSkillSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new CombatSkillSelector;
    }

    #[Test]
    public function resolve_round_skill_returns_no_skill_result_when_no_skills_available(): void
    {
        // Arrange
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $character->combat_monsters = [['hp' => 100, 'max_hp' => 100]];
        $character->save();

        // Act
        $result = $this->selector->resolveRoundSkill(
            $character,
            null,
            currentRound: 1,
            currentMana: 100,
            skillCooldowns: []
        );

        // Assert
        $this->assertEquals(100, $result['mana']);
        $this->assertFalse($result['is_aoe']);
        $this->assertEquals(0, $result['skill_damage']);
        $this->assertEmpty($result['skills_used_this_round']);
    }

    #[Test]
    public function resolve_round_skill_filters_by_requested_skill_ids(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $character->combat_monsters = [['hp' => 100, 'max_hp' => 100]];
        $character->save();

        $firstSkill = $this->createSkillDefinition(['base_damage' => 30]);
        $secondSkill = $this->createSkillDefinition(['base_damage' => 50]);
        $this->attachSkillToCharacter($character, $firstSkill);
        $this->attachSkillToCharacter($character, $secondSkill);

        $result = $this->selector->resolveRoundSkill(
            $character,
            [$firstSkill->id],
            currentRound: 1,
            currentMana: 100,
            skillCooldowns: []
        );

        $this->assertSame(30, $result['skill_damage']);
        $this->assertSame($firstSkill->id, $result['skills_used_this_round'][0]['skill_id']);
    }

    #[Test]
    public function resolve_round_skill_returns_correct_structure(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $character->combat_monsters = [['hp' => 100, 'max_hp' => 100]];
        $character->save();

        $skill = $this->createSkillDefinition(['base_damage' => 30]);
        $this->attachSkillToCharacter($character, $skill);

        $result = $this->selector->resolveRoundSkill(
            $character,
            null,
            currentRound: 1,
            currentMana: 100,
            skillCooldowns: []
        );

        $this->assertArrayHasKey('mana', $result);
        $this->assertArrayHasKey('is_aoe', $result);
        $this->assertArrayHasKey('skill_damage', $result);
        $this->assertArrayHasKey('skills_used_this_round', $result);
        $this->assertArrayHasKey('new_cooldowns', $result);
        $this->assertSame(90, $result['mana']);
        $this->assertSame(30, $result['skill_damage']);
        $this->assertSame($skill->id, $result['skills_used_this_round'][0]['skill_id']);
    }

    #[Test]
    public function resolve_round_skill_treats_empty_requested_skill_ids_as_no_restriction(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'class' => 'mage',
            'strength' => 1,
            'energy' => 3,
        ]);
        $character->combat_monsters = [['hp' => 1, 'max_hp' => 1]];
        $character->save();

        $skill = $this->createSkillDefinition([
            'name' => '小火球',
            'class_restriction' => 'mage',
            'skill_line' => 'mage_fireball',
            'base_damage' => 2,
            'mana_cost' => 8,
            'effect_key' => 'fireball',
        ]);
        $this->attachSkillToCharacter($character, $skill);

        $result = $this->selector->resolveRoundSkill(
            $character,
            [],
            currentRound: 1,
            currentMana: 20,
            skillCooldowns: []
        );

        $this->assertSame(12, $result['mana']);
        $this->assertSame(2, $result['skill_damage']);
        $this->assertSame('小火球', $result['skills_used_this_round'][0]['name']);
    }

    #[Test]
    public function resolve_round_skill_keeps_fireball_single_target_even_with_legacy_explosion_passive(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'class' => 'mage',
            'energy' => 3,
        ]);
        $character->combat_monsters = array_fill(0, 5, ['hp' => 100, 'max_hp' => 100]);
        $character->save();

        $fireball = $this->createSkillDefinition([
            'name' => '小火球',
            'class_restriction' => 'mage',
            'skill_line' => 'mage_fireball',
            'base_damage' => 16,
            'mana_cost' => 10,
            'effect_key' => 'fireball',
            'target_type' => 'single',
        ]);
        $legacyExplosionPassive = $this->createSkillDefinition([
            'name' => '强化火球术',
            'type' => 'passive',
            'class_restriction' => 'mage',
            'skill_line' => 'mage_fireball',
            'effect_key' => 'fireball',
            'effects' => ['explosion_radius_bonus' => 0.3],
            'target_type' => 'single',
            'base_damage' => 0,
            'mana_cost' => 0,
        ]);
        $this->attachSkillToCharacter($character, $fireball);
        $this->attachSkillToCharacter($character, $legacyExplosionPassive);

        $result = $this->selector->resolveRoundSkill(
            $character,
            [$fireball->id],
            currentRound: 1,
            currentMana: 50,
            skillCooldowns: []
        );

        $this->assertFalse($result['is_aoe']);
        $this->assertSame('single', $result['skills_used_this_round'][0]['target_type']);
    }

    #[Test]
    public function select_optimal_skill_returns_null_for_empty_input(): void
    {
        // Act
        $result = $this->selector->selectOptimalSkill([], 0, 0, 0, 0);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function select_optimal_skill_returns_single_skill_when_only_one_available(): void
    {
        // Arrange
        $availableSkills = [
            ['skill' => (object) ['id' => 1, 'name' => 'Single'], 'damage' => 50, 'mana_cost' => 10, 'is_aoe' => false],
        ];

        // Act
        $result = $this->selector->selectOptimalSkill($availableSkills, 1, 0, 100, 50);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(1, $result['skill']->id);
    }

    #[Test]
    public function select_optimal_skill_prefers_aoe_when_multiple_low_hp_monsters(): void
    {
        // Arrange - 3 alive monsters, 2 with low HP
        $singleSkill = ['skill' => (object) ['id' => 1], 'damage' => 30, 'mana_cost' => 5, 'is_aoe' => false];
        $aoeSkill = ['skill' => (object) ['id' => 2], 'damage' => 20, 'mana_cost' => 8, 'is_aoe' => true];

        // Act
        $result = $this->selector->selectOptimalSkill([$singleSkill, $aoeSkill], 3, 2, 50, 50);

        // Assert - should prefer AOE skill when multiple low HP monsters
        $this->assertNotNull($result);
        $this->assertTrue($result['is_aoe']);
    }

    #[Test]
    public function select_optimal_skill_prefers_efficient_skills_when_single_monster_hp_is_low(): void
    {
        // Arrange - total monster HP is low (100), char attack is 50, so 50*2=100 threshold
        $lowCostSkill = ['skill' => (object) ['id' => 1], 'damage' => 30, 'mana_cost' => 2, 'is_aoe' => false];
        $highCostSkill = ['skill' => (object) ['id' => 2], 'damage' => 80, 'mana_cost' => 20, 'is_aoe' => false];

        // Act
        $result = $this->selector->selectOptimalSkill([$lowCostSkill, $highCostSkill], 1, 0, 50, 50);

        // Assert - should prefer low mana cost skill when monster HP is low
        $this->assertNotNull($result);
        $this->assertEquals(2, $result['mana_cost']); // low cost skill
    }



    #[Test]
    public function select_optimal_skill_prefers_high_impact_aoe_when_multiple_monsters_are_alive(): void
    {
        $iceArrow = ['skill' => (object) ['id' => 1, 'name' => '冰箭'], 'damage' => 8, 'mana_cost' => 5, 'cooldown' => 0, 'is_aoe' => false];
        $meteor = ['skill' => (object) ['id' => 2, 'name' => '陨石术'], 'damage' => 150, 'mana_cost' => 42, 'cooldown' => 8, 'is_aoe' => true];
        $chainLightning = ['skill' => (object) ['id' => 3, 'name' => '连锁闪电'], 'damage' => 70, 'mana_cost' => 24, 'cooldown' => 5, 'is_aoe' => true];

        $result = $this->selector->selectOptimalSkill([$iceArrow, $chainLightning, $meteor], 3, 0, 360, 50);

        $this->assertNotNull($result);
        $this->assertSame('陨石术', $result['skill']->name);
    }

    #[Test]
    public function select_optimal_skill_uses_chain_lightning_when_meteor_is_unavailable(): void
    {
        $iceArrow = ['skill' => (object) ['id' => 1, 'name' => '冰箭'], 'damage' => 8, 'mana_cost' => 5, 'cooldown' => 0, 'is_aoe' => false];
        $chainLightning = ['skill' => (object) ['id' => 3, 'name' => '连锁闪电'], 'damage' => 70, 'mana_cost' => 24, 'cooldown' => 5, 'is_aoe' => true];

        $result = $this->selector->selectOptimalSkill([$iceArrow, $chainLightning], 3, 0, 240, 50);

        $this->assertNotNull($result);
        $this->assertSame('连锁闪电', $result['skill']->name);
    }

    #[Test]
    public function build_no_skill_round_result_returns_correct_structure(): void
    {
        // Act
        $result = $this->selector->buildNoSkillRoundResult(100, [1 => 5]);

        // Assert
        $this->assertArrayHasKey('mana', $result);
        $this->assertArrayHasKey('is_aoe', $result);
        $this->assertArrayHasKey('skill_damage', $result);
        $this->assertArrayHasKey('skills_used_this_round', $result);
        $this->assertArrayHasKey('new_cooldowns', $result);
        $this->assertEquals(100, $result['mana']);
        $this->assertFalse($result['is_aoe']);
        $this->assertEquals(0, $result['skill_damage']);
        $this->assertEmpty($result['skills_used_this_round']);
    }

    #[Test]
    public function compare_skills_by_efficiency_returns_correct_order(): void
    {
        // This tests the private method via public interface
        // High efficiency skill (damage/mana) should be preferred in normal combat
        // Arrange
        $highEfficiencySkill = ['skill' => (object) ['id' => 1], 'damage' => 100, 'mana_cost' => 10, 'is_aoe' => false];
        $lowEfficiencySkill = ['skill' => (object) ['id' => 2], 'damage' => 50, 'mana_cost' => 20, 'is_aoe' => false];

        // Act - with enough monster HP (above threshold 50*2=100)
        $result = $this->selector->selectOptimalSkill([$highEfficiencySkill, $lowEfficiencySkill], 1, 0, 200, 50);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(1, $result['skill']->id); // high efficiency skill
    }

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'TestHero',
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 100,
            'current_mana' => 50,
        ], $attributes));
    }

    private function createSkillDefinition(array $attributes = []): GameSkillDefinition
    {
        static $counter = 1;

        return GameSkillDefinition::create(array_merge([
            'name' => 'Skill ' . $counter,
            'description' => 'Test skill',
            'type' => 'active',
            'class_restriction' => 'all',
            'mana_cost' => 10,
            'cooldown' => 0,
            'base_damage' => 30,
            'effect_key' => 'skill_' . $counter,
            'target_type' => 'single',
            'is_active' => true,
            'skill_points_cost' => 1,
        ], $attributes));
    }

    private function attachSkillToCharacter(GameCharacter $character, GameSkillDefinition $skill): void
    {
        GameCharacterSkill::create([
            'character_id' => $character->id,
            'skill_id' => $skill->id,
        ]);
    }
}
