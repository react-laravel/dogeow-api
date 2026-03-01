<?php

namespace Tests\Unit\Services;

use App\Events\Chat\UserLeft;
use App\Events\Chat\UserLeftRoom;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Chat\WebSocketDisconnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class WebSocketDisconnectServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebSocketDisconnectService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'log']);
        Event::fake();

        $this->service = new WebSocketDisconnectService(Mockery::mock(ChatService::class));
    }

    public function test_handle_disconnect_marks_user_offline_in_all_online_rooms(): void
    {
        $user = User::factory()->create(['name' => 'Disconnect User']);
        $otherUser = User::factory()->create();
        $firstRoom = ChatRoom::factory()->create();
        $secondRoom = ChatRoom::factory()->create();

        $firstRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $firstRoom->id,
            'user_id' => $user->id,
        ]);
        $secondRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $secondRoom->id,
            'user_id' => $user->id,
        ]);
        ChatRoomUser::factory()->online()->create([
            'room_id' => $firstRoom->id,
            'user_id' => $otherUser->id,
        ]);

        $this->service->handleDisconnect($user->id, 'socket-1');

        $this->assertFalse($firstRoomUser->fresh()->is_online);
        $this->assertFalse($secondRoomUser->fresh()->is_online);
        $this->assertSame(1, $this->service->getRoomOnlineCount($firstRoom->id));
        $this->assertSame(0, $this->service->getRoomOnlineCount($secondRoom->id));

        Event::assertDispatched(UserLeft::class);
        Event::assertDispatched(UserLeftRoom::class);
    }

    public function test_handle_disconnect_returns_early_when_user_is_not_online_anywhere(): void
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();

        ChatRoomUser::factory()->offline()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $this->service->handleDisconnect($user->id, 'socket-2');

        Event::assertNotDispatched(UserLeft::class);
        Event::assertNotDispatched(UserLeftRoom::class);
        $this->assertFalse($this->service->isUserOnlineInRoom($user->id, $room->id));
    }

    public function test_cleanup_inactive_connections_marks_old_users_offline_and_returns_count(): void
    {
        $firstRoom = ChatRoom::factory()->create();
        $secondRoom = ChatRoom::factory()->create();
        $staleUser = User::factory()->create();
        $anotherStaleUser = User::factory()->create();
        $activeUser = User::factory()->create();

        $staleRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $firstRoom->id,
            'user_id' => $staleUser->id,
            'last_seen_at' => now()->subMinutes(10),
        ]);
        $anotherStaleRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $secondRoom->id,
            'user_id' => $anotherStaleUser->id,
            'last_seen_at' => now()->subMinutes(12),
        ]);
        $activeRoomUser = ChatRoomUser::factory()->online()->create([
            'room_id' => $secondRoom->id,
            'user_id' => $activeUser->id,
            'last_seen_at' => now()->subMinutes(2),
        ]);

        $cleanedCount = $this->service->cleanupInactiveConnections(5);

        $this->assertSame(2, $cleanedCount);
        $this->assertFalse($staleRoomUser->fresh()->is_online);
        $this->assertFalse($anotherStaleRoomUser->fresh()->is_online);
        $this->assertTrue($activeRoomUser->fresh()->is_online);
    }

    public function test_get_room_online_count_counts_only_online_users(): void
    {
        $room = ChatRoom::factory()->create();

        ChatRoomUser::factory()->online()->count(2)->create([
            'room_id' => $room->id,
        ]);
        ChatRoomUser::factory()->offline()->create([
            'room_id' => $room->id,
        ]);

        $this->assertSame(2, $this->service->getRoomOnlineCount($room->id));
    }

    public function test_is_user_online_in_room_reflects_current_status(): void
    {
        $room = ChatRoom::factory()->create();
        $user = User::factory()->create();

        ChatRoomUser::factory()->online()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($this->service->isUserOnlineInRoom($user->id, $room->id));

        ChatRoomUser::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->update(['is_online' => false]);

        $this->assertFalse($this->service->isUserOnlineInRoom($user->id, $room->id));
    }
}
