<?php

namespace Tests\Unit\Services\Game\DTOs;

use App\Models\Game\GameCharacter;
use App\Models\User;
use App\Services\Game\DTOs\RoundDetailsContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoundDetailsContextTest extends TestCase
{
    use RefreshDatabase;

    private GameCharacter $character;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->character = GameCharacter::create([
            'user_id' => $user->id,
            'name' => 'Test Character',
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
        ]);
    }

    #[Test]
    public function from_params_creates_instance_with_correct_values(): void
    {
        // Arrange
        $firstAliveMonster = ['id' => 1, 'name' => 'Monster', 'hp' => 50];
        $difficulty = ['monster_hp' => 1.0, 'monster_damage' => 1.0, 'reward' => 1.0];

        // Act
        $context = RoundDetailsContext::fromParams(
            character: $this->character,
            firstAliveMonster: $firstAliveMonster,
            charAttack: 100,
            charDefense: 50,
            charCritRate: 0.15,
            charCritDamage: 1.5,
            baseAttackDamage: 80,
            skillDamage: 20,
            critDamageAmount: 12,
            aoeDamageAmount: 0,
            totalDamageDealt: 92,
            defenseReduction: 0.1,
            totalMonsterDamage: 10,
            currentRound: 3,
            aliveMonsterCount: 2,
            monstersKilledThisRound: 1,
            isCrit: true,
            useAoe: false,
            difficulty: $difficulty
        );

        // Assert
        $this->assertInstanceOf(RoundDetailsContext::class, $context);
        $this->assertSame($this->character->id, $context->character->id);
        $this->assertEquals($firstAliveMonster, $context->firstAliveMonster);
        $this->assertEquals(100, $context->charAttack);
        $this->assertEquals(50, $context->charDefense);
        $this->assertEquals(0.15, $context->charCritRate);
        $this->assertEquals(1.5, $context->charCritDamage);
        $this->assertEquals(80, $context->baseAttackDamage);
        $this->assertEquals(20, $context->skillDamage);
        $this->assertEquals(12, $context->critDamageAmount);
        $this->assertEquals(0, $context->aoeDamageAmount);
        $this->assertEquals(92, $context->totalDamageDealt);
        $this->assertEquals(0.1, $context->defenseReduction);
        $this->assertEquals(10, $context->totalMonsterDamage);
        $this->assertEquals(3, $context->currentRound);
        $this->assertEquals(2, $context->aliveMonsterCount);
        $this->assertEquals(1, $context->monstersKilledThisRound);
        $this->assertTrue($context->isCrit);
        $this->assertFalse($context->useAoe);
        $this->assertEquals($difficulty, $context->difficulty);
    }

    #[Test]
    public function all_properties_are_readable(): void
    {
        // Arrange
        $firstAliveMonster = null;
        $difficulty = ['monster_hp' => 1.5, 'monster_damage' => 1.2, 'reward' => 1.3];

        // Act
        $context = RoundDetailsContext::fromParams(
            character: $this->character,
            firstAliveMonster: $firstAliveMonster,
            charAttack: 200,
            charDefense: 100,
            charCritRate: 0.25,
            charCritDamage: 2.0,
            baseAttackDamage: 150,
            skillDamage: 50,
            critDamageAmount: 30,
            aoeDamageAmount: 75,
            totalDamageDealt: 255,
            defenseReduction: 0.15,
            totalMonsterDamage: 25,
            currentRound: 5,
            aliveMonsterCount: 3,
            monstersKilledThisRound: 0,
            isCrit: false,
            useAoe: true,
            difficulty: $difficulty
        );

        // Assert - verify all properties are readable
        $this->assertSame($this->character->id, $context->character->id);
        $this->assertNull($context->firstAliveMonster);
        $this->assertEquals(200, $context->charAttack);
        $this->assertEquals(100, $context->charDefense);
        $this->assertEquals(0.25, $context->charCritRate);
        $this->assertEquals(2.0, $context->charCritDamage);
        $this->assertEquals(150, $context->baseAttackDamage);
        $this->assertEquals(50, $context->skillDamage);
        $this->assertEquals(30, $context->critDamageAmount);
        $this->assertEquals(75, $context->aoeDamageAmount);
        $this->assertEquals(255, $context->totalDamageDealt);
        $this->assertEquals(0.15, $context->defenseReduction);
        $this->assertEquals(25, $context->totalMonsterDamage);
        $this->assertEquals(5, $context->currentRound);
        $this->assertEquals(3, $context->aliveMonsterCount);
        $this->assertEquals(0, $context->monstersKilledThisRound);
        $this->assertFalse($context->isCrit);
        $this->assertTrue($context->useAoe);
        $this->assertEquals($difficulty, $context->difficulty);
    }

    #[Test]
    public function instance_is_readonly(): void
    {
        // Arrange
        $difficulty = ['monster_hp' => 1.0, 'monster_damage' => 1.0, 'reward' => 1.0];

        // Act
        $context = RoundDetailsContext::fromParams(
            character: $this->character,
            firstAliveMonster: null,
            charAttack: 50,
            charDefense: 25,
            charCritRate: 0.1,
            charCritDamage: 1.25,
            baseAttackDamage: 40,
            skillDamage: 10,
            critDamageAmount: 5,
            aoeDamageAmount: 0,
            totalDamageDealt: 50,
            defenseReduction: 0.05,
            totalMonsterDamage: 5,
            currentRound: 1,
            aliveMonsterCount: 1,
            monstersKilledThisRound: 0,
            isCrit: false,
            useAoe: false,
            difficulty: $difficulty
        );

        // Assert - verify the class is readonly
        $reflection = new \ReflectionClass($context);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function difficulty_array_is_stored(): void
    {
        // Arrange
        $difficulty = ['monster_hp' => 2.0, 'monster_damage' => 1.8, 'reward' => 1.5];

        // Act
        $context = RoundDetailsContext::fromParams(
            character: $this->character,
            firstAliveMonster: null,
            charAttack: 100,
            charDefense: 50,
            charCritRate: 0.2,
            charCritDamage: 1.75,
            baseAttackDamage: 80,
            skillDamage: 20,
            critDamageAmount: 16,
            aoeDamageAmount: 0,
            totalDamageDealt: 96,
            defenseReduction: 0.12,
            totalMonsterDamage: 15,
            currentRound: 4,
            aliveMonsterCount: 1,
            monstersKilledThisRound: 0,
            isCrit: true,
            useAoe: false,
            difficulty: $difficulty
        );

        // Assert
        $this->assertIsArray($context->difficulty);
        $this->assertEquals(2.0, $context->difficulty['monster_hp']);
        $this->assertEquals(1.8, $context->difficulty['monster_damage']);
        $this->assertEquals(1.5, $context->difficulty['reward']);
    }
}
