<?php

namespace Tests\Unit\Events\Game;

use App\Events\Game\GameLootDropped;
use Tests\TestCase;

class GameLootDroppedTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $loot = ['item' => 'sword', 'quantity' => 1];
        $event = new GameLootDropped(42, $loot);

        $this->assertInstanceOf(GameLootDropped::class, $event);
        $this->assertSame(42, $event->characterId);
        $this->assertSame($loot, $event->loot);
    }

    public function test_broadcast_on_returns_private_character_channel(): void
    {
        $event = new GameLootDropped(7, []);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('game.7', $channels[0]->name);
    }

    public function test_broadcast_as_returns_loot_dropped(): void
    {
        $event = new GameLootDropped(1, []);

        $this->assertSame('loot.dropped', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_loot_data(): void
    {
        $loot = ['item' => 'bone', 'copper' => 10, 'potion' => 'hp'];
        $event = new GameLootDropped(1, $loot);

        $data = $event->broadcastWith();

        $this->assertSame($loot, $data);
        $this->assertSame('bone', $data['item']);
        $this->assertSame(10, $data['copper']);
    }
}
