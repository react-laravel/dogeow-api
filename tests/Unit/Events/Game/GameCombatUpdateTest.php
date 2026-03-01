<?php

namespace Tests\Unit\Events\Game;

use App\Events\Game\GameCombatUpdate;
use Tests\TestCase;

class GameCombatUpdateTest extends TestCase
{
    public function test_event_can_be_instantiated(): void
    {
        $event = new GameCombatUpdate(42, ['victory' => true, 'rounds' => 5]);

        $this->assertInstanceOf(GameCombatUpdate::class, $event);
        $this->assertSame(42, $event->characterId);
        $this->assertSame(['victory' => true, 'rounds' => 5], $event->combatResult);
    }

    public function test_broadcast_on_returns_character_channel(): void
    {
        $event = new GameCombatUpdate(7, []);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('game.7', $channels[0]->name);
    }

    public function test_broadcast_as_returns_combat_update(): void
    {
        $event = new GameCombatUpdate(1, []);

        $this->assertSame('combat.update', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_combat_result(): void
    {
        $result = [
            'victory' => true,
            'rounds' => 3,
            'experience_gained' => 100,
        ];
        $event = new GameCombatUpdate(1, $result);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('victory', $data);
        $this->assertArrayHasKey('rounds', $data);
        $this->assertArrayHasKey('experience_gained', $data);
        $this->assertTrue($data['victory']);
        $this->assertSame(3, $data['rounds']);
        $this->assertSame(100, $data['experience_gained']);
    }

    public function test_broadcast_with_extracts_hp_mana_from_character_array(): void
    {
        $result = [
            'victory' => false,
            'character' => [
                'current_hp' => 25,
                'current_mana' => 10,
            ],
        ];
        $event = new GameCombatUpdate(1, $result);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('current_hp', $data);
        $this->assertArrayHasKey('current_mana', $data);
        $this->assertSame(25, $data['current_hp']);
        $this->assertSame(10, $data['current_mana']);
    }
}
