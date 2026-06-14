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
    public function calculate_monster_copper_loot_returns_zero_when_no_monster_id_and_chance_fails(): void
    {
        config(['game.copper_drop.chance' => 0.0]);

        $result = $this->calculator->calculateMonsterCopperLoot(['level' => 3]);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculate_monster_copper_loot_returns_map_layer_times_per_layer_when_chance_succeeds(): void
    {
        config([
            'game.copper_drop.chance' => 1.0,
            'game.copper_drop.per_layer' => 1,
        ]);

        $result = $this->calculator->calculateMonsterCopperLoot(['level' => 7, 'reward_layer' => 1]);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function calculate_monster_copper_loot_defaults_layer_to_one_without_level(): void
    {
        config([
            'game.copper_drop.chance' => 1.0,
            'game.copper_drop.per_layer' => 1,
        ]);

        $result = $this->calculator->calculateMonsterCopperLoot([]);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function roll_chance_returns_boolean(): void
    {
        config(['game.copper_drop.chance' => 1.0]);

        $result = $this->calculator->calculateMonsterCopperLoot(['level' => 2, 'map_layer' => 4]);

        $this->assertSame(4, $result);
    }
}
