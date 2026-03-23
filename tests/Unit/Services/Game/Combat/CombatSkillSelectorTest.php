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
        // Arrange
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $skill1 = $this->createSkillDefinition(['name' => 'Skill1', 'damage' => 100, 'mana_cost' => 10]);
        $skill2 = $this->createSkillDefinition(['name' => 'Skill2', 'damage' => 200, 'mana_cost' => 20]);
        $this->attachSkillToCharacter($character, $skill1);
        $this->attachSkillToCharacter($character, $skill2);
        $character->combat_monsters = [['hp' => 100, 'max_hp' => 100]];
        $character->save();

        // Act - only request skill1
        $result = $this->selector->resolveRoundSkill(
            $character,
            [$skill1->id],
            currentRound: 1,
            currentMana: 100,
            skillCooldowns: []
        );

        // Assert
        $this->assertCount(1, $result['skills_used_this_round']);
        $this->assertEquals('Skill1', $result['skills_used_this_round'][0]['name']);
        $this->assertEquals(90, $result['mana']); // 100 - 10
    }

    #[Test]
    public function resolve_round_skill_returns_correct_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $skill = $this->createSkillDefinition(['damage' => 50, 'mana_cost' => 5, 'target_type' => 'all']);
        $this->attachSkillToCharacter($character, $skill);
        $character->combat_monsters = [
            ['hp' => 100, 'max_hp' => 100, 'position' => 0],
            ['hp' => 100, 'max_hp' => 100, 'position' => 1],
        ];
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
        $this->assertArrayHasKey('mana', $result);
        $this->assertArrayHasKey('is_aoe', $result);
        $this->assertArrayHasKey('skill_damage', $result);
        $this->assertArrayHasKey('skills_used_this_round', $result);
        $this->assertArrayHasKey('new_cooldowns', $result);
        $this->assertEquals(95, $result['mana']); // 100 - 5
        $this->assertTrue($result['is_aoe']);
        $this->assertEquals(50, $result['skill_damage']);
        $this->assertCount(1, $result['skills_used_this_round']);
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
    public function select_optimal_skill_prefers_efficient_skills_when_low_monster_hp(): void
    {
        // Arrange - total monster HP is low (100), char attack is 50, so 50*2=100 threshold
        $lowCostSkill = ['skill' => (object) ['id' => 1], 'damage' => 30, 'mana_cost' => 2, 'is_aoe' => false];
        $highCostSkill = ['skill' => (object) ['id' => 2], 'damage' => 80, 'mana_cost' => 20, 'is_aoe' => false];

        // Act
        $result = $this->selector->selectOptimalSkill([$lowCostSkill, $highCostSkill], 2, 0, 50, 50);

        // Assert - should prefer low mana cost skill when monster HP is low
        $this->assertNotNull($result);
        $this->assertEquals(2, $result['mana_cost']); // low cost skill
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
            'damage' => 30,
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
