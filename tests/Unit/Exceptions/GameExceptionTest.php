<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\GameException;
use Tests\TestCase;

class GameExceptionTest extends TestCase
{
    public function test_game_exception_has_default_message(): void
    {
        $exception = new GameException(400, 'Test error');

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function test_game_exception_can_be_thrown_and_caught(): void
    {
        try {
            throw new GameException(422, 'Game validation error');
        } catch (GameException $e) {
            $this->assertEquals(422, $e->getCode());
            $this->assertEquals('Game validation error', $e->getMessage());
        }
    }
}
