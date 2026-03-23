<?php

namespace Tests\Unit\Events\Game;

use App\Events\Game\GameInventoryUpdate;
use Tests\TestCase;

class GameInventoryUpdateTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $payload = [
            'inventory' => [],
            'storage' => [],
            'equipment' => [],
            'inventory_size' => 20,
            'storage_size' => 10,
        ];
        $event = new GameInventoryUpdate(42, $payload);

        $this->assertInstanceOf(GameInventoryUpdate::class, $event);
        $this->assertSame(42, $event->characterId);
        $this->assertSame($payload, $event->payload);
    }

    public function test_broadcast_on_returns_character_channel(): void
    {
        $event = new GameInventoryUpdate(7, []);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('game.7', $channels[0]->name);
    }

    public function test_broadcast_as_returns_inventory_update(): void
    {
        $event = new GameInventoryUpdate(1, []);

        $this->assertSame('inventory.update', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_payload(): void
    {
        $payload = [
            'inventory' => [['id' => 1, 'name' => 'Sword']],
            'equipment' => [['slot' => 'weapon', 'item_id' => 1]],
        ];
        $event = new GameInventoryUpdate(1, $payload);

        $data = $event->broadcastWith();

        $this->assertSame($payload, $data);
        $this->assertSame('Sword', $data['inventory'][0]['name']);
    }
}
