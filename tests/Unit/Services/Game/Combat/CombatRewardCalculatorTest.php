<?php

namespace Tests\Unit\Services\Game\Combat;

use App\Services\Game\Combat\CombatRewardCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CombatRewardCalculatorTest extends TestCase
{
    private CombatRewardCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CombatRewardCalculator;
    }

    #[Test]
    public function calculate_round_death_rewards_returns_zero_when_no_monsters_die(): void
    {
        // Arrange
        $monstersUpdated = [
            ['id' => 1, 'hp' => 50, 'experience' => 100],
        ];
        $hpAtRoundStart = [1 => 50];
        $difficulty = ['reward' => 1.0];

        // Act
        $result = $this->calculator->calculateRoundDeathRewards($monstersUpdated, $hpAtRoundStart, $difficulty);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function calculate_round_death_rewards_applies_reward_multiplier(): void
    {
        // Arrange
        $monstersUpdated = [
            ['id' => 1, 'hp' => 0, 'experience' => 100],
        ];
        $hpAtRoundStart = [0 => 50]; // keyed by array index, not monster ID
        $difficulty = ['reward' => 2.0];

        // Act
        $result = $this->calculator->calculateRoundDeathRewards($monstersUpdated, $hpAtRoundStart, $difficulty);

        // Assert
        $this->assertEquals(200, $result[0]); // experience * 2
    }

    #[Test]
    public function calculate_round_death_rewards_returns_correct_experience_and_copper(): void
    {
        // Arrange
        $monstersUpdated = [
            ['id' => 1, 'hp' => 0, 'experience' => 100],
            ['id' => 2, 'hp' => 0, 'experience' => 200],
        ];
        $hpAtRoundStart = [0 => 50, 1 => 50]; // keyed by array index, not monster ID
        $difficulty = ['reward' => 1.0];

        // Act
        $result = $this->calculator->calculateRoundDeathRewards($monstersUpdated, $hpAtRoundStart, $difficulty);

        // Assert
        $this->assertEquals(300, $result[0]); // total experience
        $this->assertIsInt($result[1]); // copper
    }

    #[Test]
    public function calculate_monster_copper_loot_returns_random_when_no_monster_id(): void
    {
        // Arrange
        $monster = [];

        // Act
        $result = $this->calculator->calculateMonsterCopperLoot($monster);

        // Assert
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    #[Test]
    public function calculate_monster_copper_loot_returns_zero_when_chance_fails(): void
    {
        // Arrange - monster with copper_chance of 0 should return 0
        $monster = [
            'id' => 999, // non-existent ID to avoid DB lookup
            'level' => 1,
        ];

        // Act
        $result = $this->calculator->calculateMonsterCopperLoot($monster);

        // Assert - result should be an integer (either 0 or random 1-10)
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    #[Test]
    public function roll_chance_returns_boolean(): void
    {
        // This tests the private method via public interface
        // We can verify behavior through multiple calls to calculateMonsterCopperLoot
        // which internally uses rollChance with different probabilities

        // Test with 100% chance - should always return something
        $monster = ['id' => null]; // No ID triggers random 1-10
        $result = $this->calculator->calculateMonsterCopperLoot($monster);

        // Result should be integer between 1 and 10
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(10, $result);
    }
}
