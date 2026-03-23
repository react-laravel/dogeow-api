<?php

namespace Tests\Unit\Services\Game\Combat;

use App\Services\Game\Combat\CombatDamageCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CombatDamageCalculatorTest extends TestCase
{
    private CombatDamageCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CombatDamageCalculator;
    }

    #[Test]
    public function apply_character_damage_to_monsters_returns_updated_monsters_and_total_damage(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'defense' => 10, 'position' => 0, 'name' => 'Monster1'],
            ['hp' => 100, 'defense' => 10, 'position' => 1, 'name' => 'Monster2'],
        ];
        $targetMonsters = [['position' => 0]];

        // Act
        $result = $this->calculator->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            charAttack: 50,
            skillDamage: 0,
            isCrit: false,
            charCritDamage: 1.5,
            useAoe: false
        );

        // Assert
        [$updatedMonsters, $totalDamage] = $result;
        $this->assertIsArray($updatedMonsters);
        $this->assertIsInt($totalDamage);
        $this->assertGreaterThan(0, $totalDamage);
        // First monster should be targeted and have reduced HP
        $this->assertLessThan(100, $updatedMonsters[0]['hp']);
        $this->assertTrue($updatedMonsters[0]['was_attacked']);
        $this->assertEquals(100, $updatedMonsters[1]['hp']); // Second monster not targeted
        $this->assertFalse($updatedMonsters[1]['was_attacked']);
    }

    #[Test]
    public function apply_character_damage_to_monsters_skips_new_monsters(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'defense' => 10, 'position' => 0, 'is_new' => true, 'name' => 'NewMonster'],
            ['hp' => 100, 'defense' => 10, 'position' => 1, 'name' => 'OldMonster'],
        ];
        $targetMonsters = [['position' => 0], ['position' => 1]];

        // Act
        $result = $this->calculator->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            charAttack: 50,
            skillDamage: 0,
            isCrit: false,
            charCritDamage: 1.5,
            useAoe: false
        );

        // Assert
        [$updatedMonsters, $totalDamage] = $result;
        // New monster should keep original HP (not attacked)
        $this->assertEquals(100, $updatedMonsters[0]['hp']);
        // Old monster should have reduced HP
        $this->assertLessThan(100, $updatedMonsters[1]['hp']);
        // is_new flag should be cleared after processing
        $this->assertArrayNotHasKey('is_new', $updatedMonsters[0]);
    }

    #[Test]
    public function apply_character_damage_to_monsters_applies_aoe_multiplier_when_use_aoe(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'defense' => 0, 'position' => 0, 'name' => 'Monster1'],
            ['hp' => 100, 'defense' => 0, 'position' => 1, 'name' => 'Monster2'],
        ];
        $targetMonsters = [['position' => 0], ['position' => 1]];

        // Act - with AOE (useAoe: true)
        [$updatedMonstersAoe, $totalDamageAoe] = $this->calculator->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            charAttack: 100,
            skillDamage: 0,
            isCrit: false,
            charCritDamage: 1.5,
            useAoe: true
        );

        // Act - without AOE (useAoe: false)
        [$updatedMonstersNoAoe, $totalDamageNoAoe] = $this->calculator->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            charAttack: 100,
            skillDamage: 0,
            isCrit: false,
            charCritDamage: 1.5,
            useAoe: false
        );

        // Assert - AOE damage should be less due to multiplier
        $this->assertLessThan($totalDamageNoAoe, $totalDamageAoe);
        // AOE multiplier is 0.7 from config
        $this->assertEquals(70, $totalDamageAoe); // 100 * 0.7 = 70
        $this->assertEquals(100, $totalDamageNoAoe); // 100 * 1.0 = 100
    }

    #[Test]
    public function apply_character_damage_to_monsters_clears_is_new_flag(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'defense' => 10, 'position' => 0, 'is_new' => true, 'name' => 'NewMonster'],
        ];
        $targetMonsters = [['position' => 0]];

        // Act
        $result = $this->calculator->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            charAttack: 50,
            skillDamage: 0,
            isCrit: false,
            charCritDamage: 1.5,
            useAoe: false
        );

        // Assert
        [$updatedMonsters, ] = $result;
        $this->assertArrayNotHasKey('is_new', $updatedMonsters[0]);
    }

    #[Test]
    public function compute_base_attack_damage_returns_zero_for_empty_targets(): void
    {
        // Act
        $result = $this->calculator->computeBaseAttackDamage([], 0, 100, 1.5, false, 0.5);

        // Assert
        $this->assertEquals([0, 0], $result);
    }

    #[Test]
    public function compute_base_attack_damage_returns_skill_damage_when_skill_damage_provided(): void
    {
        // Arrange
        $targets = [['position' => 0, 'defense' => 10]];

        // Act
        $result = $this->calculator->computeBaseAttackDamage($targets, 50, 100, 1.5, false, 0.5);

        // Assert
        $this->assertEquals([50, 0], $result);
    }

    #[Test]
    public function compute_base_attack_damage_applies_crit_when_is_crit_true(): void
    {
        // Arrange
        $targets = [['position' => 0, 'defense' => 10]];

        // Act
        $result = $this->calculator->computeBaseAttackDamage($targets, 0, 100, 1.5, true, 0.5);

        // Assert
        // Base damage = 100 - 10 * 0.5 = 95
        // With crit: 95 * 1.5 = 142 (rounded)
        // Crit bonus: 95 * 0.5 = 47
        [$damage, $critBonus] = $result;
        $this->assertEquals(142, $damage);
        $this->assertEquals(47, $critBonus);
    }

    #[Test]
    public function calculate_monster_counter_damage_returns_total_counter_damage(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'attack' => 30, 'position' => 0],
            ['hp' => 100, 'attack' => 20, 'position' => 1],
        ];

        // Act
        $result = $this->calculator->calculateMonsterCounterDamage($monsters, charDefense: 10);

        // Assert
        // Monster 1: 30 - 10 * 0.3 = 27
        // Monster 2: 20 - 10 * 0.3 = 17
        // Total: 44
        $this->assertEquals(44, $result);
    }

    #[Test]
    public function calculate_monster_counter_damage_excludes_dead_monsters(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 0, 'attack' => 30, 'position' => 0], // Dead
            ['hp' => 100, 'attack' => 20, 'position' => 1], // Alive
        ];

        // Act
        $result = $this->calculator->calculateMonsterCounterDamage($monsters, charDefense: 10);

        // Assert - only alive monster counts
        // Monster 2: 20 - 10 * 0.3 = 17
        $this->assertEquals(17, $result);
    }

    #[Test]
    public function is_monster_in_targets_returns_true_when_position_matches(): void
    {
        // Arrange
        $monster = ['position' => 2];
        $targets = [['position' => 1], ['position' => 2]];

        // Act
        $result = $this->calculator->isMonsterInTargets($monster, $targets);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function is_monster_in_targets_returns_false_when_no_match(): void
    {
        // Arrange
        $monster = ['position' => 3];
        $targets = [['position' => 1], ['position' => 2]];

        // Act
        $result = $this->calculator->isMonsterInTargets($monster, $targets);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function is_monster_in_targets_returns_false_when_no_position(): void
    {
        // Arrange
        $monster = [];
        $targets = [['position' => 1]];

        // Act
        $result = $this->calculator->isMonsterInTargets($monster, $targets);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function select_round_targets_returns_empty_array_when_no_alive_monsters(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 0],
        ];

        // Act
        $result = $this->calculator->selectRoundTargets($monsters, false);

        // Assert
        $this->assertEmpty($result);
    }

    #[Test]
    public function select_round_targets_returns_single_target_when_not_aoe(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'position' => 0],
            ['hp' => 100, 'position' => 1],
        ];

        // Act
        $result = $this->calculator->selectRoundTargets($monsters, false);

        // Assert - should return single target
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('hp', $result[0]);
        $this->assertEquals(100, $result[0]['hp']);
    }

    #[Test]
    public function select_round_targets_returns_all_alive_monsters_when_aoe(): void
    {
        // Arrange
        $monsters = [
            ['hp' => 100, 'position' => 0],
            ['hp' => 0, 'position' => 1], // Dead
            ['hp' => 100, 'position' => 2],
        ];

        // Act
        $result = $this->calculator->selectRoundTargets($monsters, true);

        // Assert - should return all alive monsters
        $this->assertCount(2, $result);
    }

    #[Test]
    public function get_skill_target_positions_extracts_positions_from_targets(): void
    {
        // Arrange
        $targets = [
            ['position' => 1],
            ['position' => 3],
            [],
        ];

        // Act
        $result = $this->calculator->getSkillTargetPositions($targets);

        // Assert
        $this->assertEquals([1, 3], $result);
    }

    #[Test]
    public function roll_chance_for_processor_returns_boolean(): void
    {
        // Act
        $result = $this->calculator->rollChanceForProcessor(0.5);

        // Assert
        $this->assertIsBool($result);
    }
}
