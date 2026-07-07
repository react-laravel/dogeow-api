<?php

namespace Tests\Feature\Controllers\Monopoly;

use App\Events\Monopoly\MonopolyLobbyUpdated;
use App\Events\Monopoly\MonopolyStateUpdated;
use App\Models\Monopoly\MonopolyPlayer;
use App\Models\Monopoly\MonopolyRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonopolyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->host = User::factory()->create(['name' => 'Host']);
        Sanctum::actingAs($this->host);
    }

    #[Test]
    public function create_room_initializes_host_player_and_properties(): void
    {
        $response = $this->postJson('/api/monopoly/rooms', ['name' => '周末大富翁']);

        $response->assertCreated()
            ->assertJsonPath('data.state.room.name', '周末大富翁')
            ->assertJsonPath('data.state.room.max_rounds', 30)
            ->assertJsonPath('data.state.players.0.cash', 8000000);

        $this->assertDatabaseHas('monopoly_rooms', ['name' => '周末大富翁']);
        $this->assertDatabaseHas('monopoly_players', [
            'user_id' => $this->host->id,
            'is_host' => true,
            'cash' => 8000000,
        ]);
        $this->assertDatabaseHas('monopoly_properties', ['name' => '罗马']);
        Event::assertDispatched(MonopolyStateUpdated::class);
        Event::assertDispatched(MonopolyLobbyUpdated::class);
    }

    #[Test]
    public function join_add_computer_and_start_game(): void
    {
        $roomId = $this->postJson('/api/monopoly/rooms', ['name' => '测试房'])->json('data.room.id');
        $guest = User::factory()->create(['name' => 'Guest']);

        Sanctum::actingAs($guest);
        $this->postJson("/api/monopoly/rooms/{$roomId}/join")->assertOk()
            ->assertJsonPath('data.player.name', 'Guest');

        Sanctum::actingAs($this->host);
        $this->postJson("/api/monopoly/rooms/{$roomId}/computers")->assertOk()
            ->assertJsonPath('data.player.type', 'computer');

        $this->postJson("/api/monopoly/rooms/{$roomId}/start")->assertOk()
            ->assertJsonPath('data.state.room.status', 'playing');

        $this->assertDatabaseHas('monopoly_rooms', [
            'id' => $roomId,
            'status' => 'playing',
        ]);
        $this->assertDatabaseCount('monopoly_players', 3);
    }

    #[Test]
    public function non_member_cannot_read_room_state(): void
    {
        $roomId = $this->postJson('/api/monopoly/rooms', ['name' => '私人房'])->json('data.room.id');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/monopoly/rooms/{$roomId}")->assertForbidden();
    }

    #[Test]
    public function roll_pays_start_bonus_when_passing_start(): void
    {
        $roomId = $this->postJson('/api/monopoly/rooms', ['name' => '起点测试'])->json('data.room.id');
        $this->postJson("/api/monopoly/rooms/{$roomId}/computers")->assertOk();
        $this->postJson("/api/monopoly/rooms/{$roomId}/start")->assertOk();

        $player = MonopolyPlayer::where('room_id', $roomId)->where('user_id', $this->host->id)->firstOrFail();
        $player->update(['position' => 27]);

        $this->postJson("/api/monopoly/rooms/{$roomId}/roll")->assertOk();

        $this->assertGreaterThanOrEqual(10_000_000, $player->fresh()->cash);
        $this->assertDatabaseHas('monopoly_events', [
            'room_id' => $roomId,
            'type' => 'start.bonus',
        ]);
    }

    #[Test]
    public function buy_and_build_respects_house_limits(): void
    {
        $roomId = $this->postJson('/api/monopoly/rooms', ['name' => '建房测试'])->json('data.room.id');
        $this->postJson("/api/monopoly/rooms/{$roomId}/computers")->assertOk();
        $this->postJson("/api/monopoly/rooms/{$roomId}/start")->assertOk();

        $player = MonopolyPlayer::where('room_id', $roomId)->where('user_id', $this->host->id)->firstOrFail();
        $player->update(['position' => 1, 'cash' => 5_000_000]);

        $buy = $this->postJson("/api/monopoly/rooms/{$roomId}/buy")->assertOk();
        $propertyId = $buy->json('data.property.id');
        $buy->assertJsonPath('data.property.price', 100000)
            ->assertJsonPath('data.property.house_price', 500000);

        $this->postJson("/api/monopoly/rooms/{$roomId}/build", [
            'property_id' => $propertyId,
            'houses' => 2,
        ])->assertOk()
            ->assertJsonPath('data.property.houses', 2)
            ->assertJsonPath('data.state.properties.0.current_rent', 110000);

        $this->postJson("/api/monopoly/rooms/{$roomId}/build", [
            'property_id' => $propertyId,
            'houses' => 2,
        ])->assertOk()->assertJsonPath('data.property.houses', 4);

        $this->postJson("/api/monopoly/rooms/{$roomId}/build", [
            'property_id' => $propertyId,
            'houses' => 2,
        ])->assertUnprocessable();
    }

    #[Test]
    public function computer_turn_auto_advances_to_next_human(): void
    {
        $roomId = $this->postJson('/api/monopoly/rooms', ['name' => '人机测试'])->json('data.room.id');
        $guest = User::factory()->create(['name' => 'Guest']);

        $this->postJson("/api/monopoly/rooms/{$roomId}/computers")->assertOk();
        Sanctum::actingAs($guest);
        $this->postJson("/api/monopoly/rooms/{$roomId}/join")->assertOk();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/monopoly/rooms/{$roomId}/start")->assertOk();
        $this->postJson("/api/monopoly/rooms/{$roomId}/roll")->assertOk();
        $this->postJson("/api/monopoly/rooms/{$roomId}/end-turn")->assertOk();

        $room = MonopolyRoom::findOrFail($roomId);
        $guestPlayer = MonopolyPlayer::where('room_id', $roomId)->where('user_id', $guest->id)->firstOrFail();
        $this->assertSame($guestPlayer->turn_order, $room->current_turn_order);
    }

    #[Test]
    public function game_finishes_by_net_worth_after_max_rounds(): void
    {
        config(['monopoly.max_rounds' => 1]);

        $roomId = $this->postJson('/api/monopoly/rooms', ['name' => '限时测试'])->json('data.room.id');
        $this->postJson("/api/monopoly/rooms/{$roomId}/computers")->assertOk();
        $this->postJson("/api/monopoly/rooms/{$roomId}/start")->assertOk()
            ->assertJsonPath('data.state.room.max_rounds', 1);

        $this->postJson("/api/monopoly/rooms/{$roomId}/roll")->assertOk();
        $this->postJson("/api/monopoly/rooms/{$roomId}/end-turn")->assertOk()
            ->assertJsonPath('data.state.room.status', 'finished');

        $room = MonopolyRoom::findOrFail($roomId);
        $this->assertSame('finished', $room->status);
        $this->assertDatabaseHas('monopoly_events', [
            'room_id' => $roomId,
            'type' => 'game.finished',
        ]);
    }
}
