<?php

namespace Tests\Unit\Services\Game\DTOs;

use App\Models\Game\GameMonsterDefinition;
use App\Services\Game\DTOs\DefeatContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DefeatContextTest extends TestCase
{
    #[Test]
    public function from_params_creates_instance_with_correct_values(): void
    {
        // Arrange
        $monster = GameMonsterDefinition::create([
            'name' => 'Test Monster',
            'type' => 'normal',
            'level' => 10,
            'hp_base' => 100,
            'attack_base' => 20,
            'defense_base' => 5,
            'experience_base' => 50,
        ]);
        $monsterLevel = 10;
        $monsterMaxHp = 100;
        $monsterHpBeforeRound = 75;

        // Act
        $context = DefeatContext::fromParams(
            monster: $monster,
            monsterLevel: $monsterLevel,
            monsterMaxHp: $monsterMaxHp,
            monsterHpBeforeRound: $monsterHpBeforeRound
        );

        // Assert
        $this->assertInstanceOf(DefeatContext::class, $context);
        $this->assertSame($monster->id, $context->monster->id);
        $this->assertEquals($monsterLevel, $context->monsterLevel);
        $this->assertEquals($monsterMaxHp, $context->monsterMaxHp);
        $this->assertEquals($monsterHpBeforeRound, $context->monsterHpBeforeRound);
    }

    #[Test]
    public function properties_are_readable(): void
    {
        // Arrange
        $monster = GameMonsterDefinition::create([
            'name' => 'Slime',
            'type' => 'normal',
            'level' => 5,
            'hp_base' => 50,
            'attack_base' => 10,
            'defense_base' => 2,
            'experience_base' => 25,
        ]);

        // Act
        $context = DefeatContext::fromParams(
            monster: $monster,
            monsterLevel: 5,
            monsterMaxHp: 50,
            monsterHpBeforeRound: 30
        );

        // Assert - all properties should be readable
        $this->assertEquals('Slime', $context->monster->name);
        $this->assertEquals(5, $context->monsterLevel);
        $this->assertEquals(50, $context->monsterMaxHp);
        $this->assertEquals(30, $context->monsterHpBeforeRound);
    }

    #[Test]
    public function instance_is_readonly(): void
    {
        // Arrange
        $monster = GameMonsterDefinition::create([
            'name' => 'Goblin',
            'type' => 'normal',
            'level' => 3,
            'hp_base' => 30,
            'attack_base' => 8,
            'defense_base' => 1,
            'experience_base' => 15,
        ]);

        // Act
        $context = DefeatContext::fromParams(
            monster: $monster,
            monsterLevel: 3,
            monsterMaxHp: 30,
            monsterHpBeforeRound: 15
        );

        // Assert - verify the class is readonly
        $reflection = new \ReflectionClass($context);
        $this->assertTrue($reflection->isReadOnly());
    }
}
