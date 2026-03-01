<?php

namespace Tests\Unit;

use App\Exceptions\GameException;
use Tests\TestCase;

class GameExceptionTest extends TestCase
{
    public function test_exception_has_correct_code(): void
    {
        $exception = new GameException(GameException::CODE_CHARACTER_NOT_FOUND);

        $this->assertEquals(GameException::CODE_CHARACTER_NOT_FOUND, $exception->getCode());
    }

    public function test_exception_has_correct_message(): void
    {
        $exception = new GameException(GameException::CODE_CHARACTER_NOT_FOUND);

        $this->assertEquals('Character not found', $exception->getMessage());
    }

    public function test_exception_can_have_custom_message(): void
    {
        $exception = new GameException(
            GameException::CODE_CHARACTER_NOT_FOUND,
            'Custom error message'
        );

        $this->assertEquals('Custom error message', $exception->getMessage());
    }

    public function test_exception_to_response_array(): void
    {
        $exception = new GameException(
            GameException::CODE_INSUFFICIENT_RESOURCES,
            'Not enough copper'
        );

        $response = $exception->toResponseArray();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertFalse($response['success']);
        $this->assertEquals(GameException::CODE_INSUFFICIENT_RESOURCES, $response['code']);
        $this->assertEquals('Not enough copper', $response['message']);
    }

    public function test_static_factory_character_not_found(): void
    {
        $exception = GameException::characterNotFound();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_CHARACTER_NOT_FOUND, $exception->getCode());
    }

    public function test_static_factory_insufficient_level(): void
    {
        $exception = GameException::insufficientLevel();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_INSUFFICIENT_LEVEL, $exception->getCode());
    }

    public function test_static_factory_insufficient_resources(): void
    {
        $exception = GameException::insufficientResources();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_INSUFFICIENT_RESOURCES, $exception->getCode());
    }

    public function test_static_factory_combat_not_in_progress(): void
    {
        $exception = GameException::combatNotInProgress();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_COMBAT_NOT_IN_PROGRESS, $exception->getCode());
    }

    public function test_static_factory_invalid_skill(): void
    {
        $exception = GameException::invalidSkill();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_INVALID_SKILL, $exception->getCode());
    }

    public function test_static_factory_skill_on_cooldown(): void
    {
        $exception = GameException::skillOnCooldown();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_SKILL_ON_COOLDOWN, $exception->getCode());
    }

    public function test_static_factory_insufficient_mana(): void
    {
        $exception = GameException::insufficientMana();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_INSUFFICIENT_MANA, $exception->getCode());
    }

    public function test_static_factory_invalid_operation(): void
    {
        $exception = GameException::invalidOperation();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_INVALID_OPERATION, $exception->getCode());
    }

    public function test_static_factory_map_not_found(): void
    {
        $exception = GameException::mapNotFound();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_MAP_NOT_FOUND, $exception->getCode());
    }

    public function test_static_factory_monster_not_found(): void
    {
        $exception = GameException::monsterNotFound();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_MONSTER_NOT_FOUND, $exception->getCode());
    }

    public function test_static_factory_invalid_difficulty(): void
    {
        $exception = GameException::invalidDifficulty();

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertEquals(GameException::CODE_INVALID_DIFFICULTY, $exception->getCode());
    }

    public function test_get_error_code_returns_code(): void
    {
        $exception = new GameException(GameException::CODE_CHARACTER_NOT_FOUND);

        $this->assertEquals(GameException::CODE_CHARACTER_NOT_FOUND, $exception->getErrorCode());
    }

    public function test_static_factory_accepts_custom_message(): void
    {
        $exception = GameException::characterNotFound('角色 ID 999 不存在');

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertSame('角色 ID 999 不存在', $exception->getMessage());
    }

    public function test_static_factory_accepts_previous_exception(): void
    {
        $previous = new \RuntimeException('DB error');
        $exception = GameException::mapNotFound(null, $previous);

        $this->assertInstanceOf(GameException::class, $exception);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_unknown_code_uses_default_message(): void
    {
        $exception = new GameException(99999);

        $this->assertSame('Unknown game error', $exception->getMessage());
    }
}
