<?php

namespace Tests\Unit\Events\Game;

use App\Events\Game\GameLevelUp;
use Tests\TestCase;

class GameLevelUpTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $event = new GameLevelUp(42, 11, 1, 5);

        $this->assertInstanceOf(GameLevelUp::class, $event);
        $this->assertSame(42, $event->characterId);
        $this->assertSame(11, $event->newLevel);
        $this->assertSame(1, $event->skillPointsGained);
        $this->assertSame(5, $event->statPointsGained);
    }

    public function test_event_uses_default_values(): void
    {
        $event = new GameLevelUp(1, 2);

        $this->assertSame(1, $event->skillPointsGained);
        $this->assertSame(5, $event->statPointsGained);
    }

    public function test_broadcast_on_returns_private_character_channel(): void
    {
        $event = new GameLevelUp(7, 10);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('game.7', $channels[0]->name);
    }

    public function test_broadcast_as_returns_level_up(): void
    {
        $event = new GameLevelUp(1, 5);

        $this->assertSame('level.up', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_level_and_character(): void
    {
        $event = new GameLevelUp(1, 15, 2, 10);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('level', $data);
        $this->assertArrayHasKey('character', $data);
        $this->assertSame(15, $data['level']);
        $this->assertSame(15, $data['character']['level']);
    }
}
