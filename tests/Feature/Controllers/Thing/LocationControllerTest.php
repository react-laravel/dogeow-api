<?php

namespace Tests\Feature\Controllers\Thing;

use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // ==================== Area Tests ====================

    public function test_area_index_returns_user_areas()
    {
        $userArea = Area::factory()->create(['user_id' => $this->user->id]);
        $otherUserArea = Area::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/areas');

        $response->assertStatus(200)
            ->assertJsonPath('data.areas.0.id', $userArea->id);
        $areas = $response->json('data.areas');
        $this->assertCount(1, $areas);
        $this->assertNotContains($otherUserArea->id, array_column($areas, 'id'));
    }

    public function test_area_store_creates_new_area()
    {
        $data = ['name' => 'Test Area'];

        $response = $this->postJson('/api/areas', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '区域创建成功',
                'data' => [
                    'area' => [
                        'name' => 'Test Area',
                        'user_id' => $this->user->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('thing_areas', [
            'name' => 'Test Area',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_area_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/areas', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_area_store_validation_fails_with_long_name()
    {
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->postJson('/api/areas', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_area_show_returns_area()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/areas/{$area->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.area.id', $area->id)
            ->assertJsonPath('data.area.name', $area->name);
    }

    public function test_area_show_returns_403_for_other_user_area()
    {
        $area = Area::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/areas/{$area->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此区域']);
    }

    public function test_area_update_modifies_area()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 'Updated Area'];

        $response = $this->putJson("/api/areas/{$area->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '区域更新成功',
                'data' => [
                    'area' => [
                        'id' => $area->id,
                        'name' => 'Updated Area',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('thing_areas', [
            'id' => $area->id,
            'name' => 'Updated Area',
        ]);
    }

    public function test_area_update_returns_403_for_other_user_area()
    {
        $area = Area::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Area'];

        $response = $this->putJson("/api/areas/{$area->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权更新此区域']);
    }

    public function test_area_destroy_deletes_area()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/areas/{$area->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => '区域删除成功']);

        $this->assertDatabaseMissing('thing_areas', ['id' => $area->id]);
    }

    public function test_area_destroy_returns_403_for_other_user_area()
    {
        $area = Area::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/areas/{$area->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权删除此区域']);
    }

    public function test_area_destroy_returns_400_when_area_has_rooms()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        Room::factory()->create(['area_id' => $area->id, 'user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/areas/{$area->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除已有房间的区域']);
    }

    public function test_set_default_area_success()
    {
        $area1 = Area::factory()->create(['user_id' => $this->user->id, 'is_default' => true]);
        $area2 = Area::factory()->create(['user_id' => $this->user->id, 'is_default' => false]);

        $response = $this->postJson("/api/areas/{$area2->id}/set-default");

        $response->assertStatus(200)
            ->assertJson(['message' => '默认区域设置成功']);

        $this->assertDatabaseHas('thing_areas', ['id' => $area1->id, 'is_default' => false]);
        $this->assertDatabaseHas('thing_areas', ['id' => $area2->id, 'is_default' => true]);
    }

    public function test_set_default_area_returns_403_for_other_user_area()
    {
        $area = Area::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->postJson("/api/areas/{$area->id}/set-default");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权设置此区域为默认']);
    }

    // ==================== Room Tests ====================

    public function test_room_index_returns_user_rooms()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $userRoom = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $otherUserRoom = Room::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200);
        $rooms = $response->json('data.rooms');
        $this->assertCount(1, $rooms);
        $this->assertEquals($userRoom->id, $rooms[0]['id']);
        $this->assertNotContains($otherUserRoom->id, array_column($rooms, 'id'));
    }

    public function test_room_index_filters_by_area_id()
    {
        $area1 = Area::factory()->create(['user_id' => $this->user->id]);
        $area2 = Area::factory()->create(['user_id' => $this->user->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area1->id]);
        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area2->id]);

        $response = $this->getJson("/api/rooms?area_id={$area1->id}");

        $response->assertStatus(200);
        $rooms = $response->json('data.rooms');
        $this->assertCount(1, $rooms);
        $this->assertEquals($room1->id, $rooms[0]['id']);
    }

    public function test_room_index_returns_empty_when_area_has_no_rooms()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/rooms?area_id={$area->id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.rooms'));
    }

    public function test_room_store_creates_new_room()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $data = [
            'name' => 'Test Room',
            'area_id' => $area->id,
        ];

        $response = $this->postJson('/api/rooms', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '房间创建成功',
                'data' => [
                    'room' => [
                        'name' => 'Test Room',
                        'area_id' => $area->id,
                        'user_id' => $this->user->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('thing_rooms', [
            'name' => 'Test Room',
            'area_id' => $area->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_room_store_returns_403_for_other_user_area()
    {
        $area = Area::factory()->create(['user_id' => $this->otherUser->id]);
        $data = [
            'name' => 'Test Room',
            'area_id' => $area->id,
        ];

        $response = $this->postJson('/api/rooms', $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权在此区域创建房间']);
    }

    public function test_room_store_validation_fails_without_required_fields()
    {
        $response = $this->postJson('/api/rooms', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'area_id']);
    }

    public function test_room_store_validation_fails_with_invalid_area_id()
    {
        $data = [
            'name' => 'Test Room',
            'area_id' => 99999,
        ];

        $response = $this->postJson('/api/rooms', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['area_id']);
    }

    public function test_room_show_returns_room()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.room.id', $room->id)
            ->assertJsonPath('data.room.name', $room->name);
    }

    public function test_room_show_returns_403_for_other_user_room()
    {
        $room = Room::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此房间']);
    }

    public function test_room_update_modifies_room()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $data = ['name' => 'Updated Room'];

        $response = $this->putJson("/api/rooms/{$room->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '房间更新成功',
                'data' => [
                    'room' => [
                        'id' => $room->id,
                        'name' => 'Updated Room',
                    ],
                ],
            ]);
    }

    public function test_room_update_returns_403_for_other_user_room()
    {
        $room = Room::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Room'];

        $response = $this->putJson("/api/rooms/{$room->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权更新此房间']);
    }

    public function test_room_update_returns_403_for_invalid_area()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $otherUserArea = Area::factory()->create(['user_id' => $this->otherUser->id]);
        $data = [
            'name' => 'Updated Room',
            'area_id' => $otherUserArea->id,
        ];

        $response = $this->putJson("/api/rooms/{$room->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权将房间移动到此区域']);
    }

    public function test_room_update_with_partial_data()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $data = ['name' => 'Updated Room'];

        $response = $this->putJson("/api/rooms/{$room->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '房间更新成功',
                'data' => [
                    'room' => [
                        'id' => $room->id,
                        'name' => 'Updated Room',
                    ],
                ],
            ]);
    }

    public function test_room_update_can_move_to_own_other_area(): void
    {
        $area1 = Area::factory()->create(['user_id' => $this->user->id]);
        $area2 = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area1->id]);

        $response = $this->putJson("/api/rooms/{$room->id}", [
            'name' => $room->name,
            'area_id' => $area2->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.room.area_id', $area2->id);
        $this->assertDatabaseHas('thing_rooms', ['id' => $room->id, 'area_id' => $area2->id]);
    }

    public function test_room_destroy_deletes_room()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        $response = $this->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => '房间删除成功']);

        $this->assertDatabaseMissing('thing_rooms', ['id' => $room->id]);
    }

    public function test_room_destroy_returns_403_for_other_user_room()
    {
        $room = Room::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权删除此房间']);
    }

    public function test_room_destroy_returns_400_when_room_has_spots()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        Spot::factory()->create(['room_id' => $room->id, 'user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/rooms/{$room->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除已有具体位置的房间']);
    }

    // ==================== Spot Tests ====================

    public function test_spot_index_returns_user_spots()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $userSpot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $otherUserSpot = Spot::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/spots');

        $response->assertStatus(200);
        $spots = $response->json('data.spots');
        $this->assertCount(1, $spots);
        $this->assertEquals($userSpot->id, $spots[0]['id']);
        $this->assertNotContains($otherUserSpot->id, array_column($spots, 'id'));
    }

    public function test_spot_index_filters_by_room_id()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot1 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room1->id]);
        $spot2 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room2->id]);

        $response = $this->getJson("/api/spots?room_id={$room1->id}");

        $response->assertStatus(200);
        $spots = $response->json('data.spots');
        $this->assertCount(1, $spots);
        $this->assertEquals($spot1->id, $spots[0]['id']);
    }

    public function test_spot_index_returns_empty_when_room_has_no_spots()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        $response = $this->getJson("/api/spots?room_id={$room->id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.spots'));
    }

    public function test_spot_store_creates_new_spot()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $data = [
            'name' => 'Test Spot',
            'room_id' => $room->id,
        ];

        $response = $this->postJson('/api/spots', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '具体位置创建成功',
                'data' => [
                    'spot' => [
                        'name' => 'Test Spot',
                        'room_id' => $room->id,
                        'user_id' => $this->user->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('thing_spots', [
            'name' => 'Test Spot',
            'room_id' => $room->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_spot_store_returns_403_for_other_user_room()
    {
        $room = Room::factory()->create(['user_id' => $this->otherUser->id]);
        $data = [
            'name' => 'Test Spot',
            'room_id' => $room->id,
        ];

        $response = $this->postJson('/api/spots', $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权在此房间创建具体位置']);
    }

    public function test_spot_store_validation_fails_without_required_fields()
    {
        $response = $this->postJson('/api/spots', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'room_id']);
    }

    public function test_spot_store_validation_fails_with_invalid_room_id()
    {
        $data = [
            'name' => 'Test Spot',
            'room_id' => 99999,
        ];

        $response = $this->postJson('/api/spots', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_spot_show_returns_spot()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $response = $this->getJson("/api/spots/{$spot->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.spot.id', $spot->id)
            ->assertJsonPath('data.spot.name', $spot->name);
    }

    public function test_spot_show_returns_403_for_other_user_spot()
    {
        $spot = Spot::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/spots/{$spot->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此具体位置']);
    }

    public function test_spot_update_modifies_spot()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $data = ['name' => 'Updated Spot'];

        $response = $this->putJson("/api/spots/{$spot->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '具体位置更新成功',
                'data' => [
                    'spot' => [
                        'id' => $spot->id,
                        'name' => 'Updated Spot',
                    ],
                ],
            ]);
    }

    public function test_spot_update_returns_403_for_other_user_spot()
    {
        $spot = Spot::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Spot'];

        $response = $this->putJson("/api/spots/{$spot->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权更新此具体位置']);
    }

    public function test_spot_update_returns_403_for_invalid_room()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $otherUserRoom = Room::factory()->create(['user_id' => $this->otherUser->id]);
        $data = [
            'name' => 'Updated Spot',
            'room_id' => $otherUserRoom->id,
        ];

        $response = $this->putJson("/api/spots/{$spot->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权将具体位置移动到此房间']);
    }

    public function test_spot_update_with_partial_data()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $data = ['name' => 'Updated Spot'];

        $response = $this->putJson("/api/spots/{$spot->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '具体位置更新成功',
                'data' => [
                    'spot' => [
                        'id' => $spot->id,
                        'name' => 'Updated Spot',
                    ],
                ],
            ]);
    }

    public function test_spot_update_can_move_to_own_other_room(): void
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room1->id]);

        $response = $this->putJson("/api/spots/{$spot->id}", [
            'name' => $spot->name,
            'room_id' => $room2->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.spot.room_id', $room2->id);
        $this->assertDatabaseHas('thing_spots', ['id' => $spot->id, 'room_id' => $room2->id]);
    }

    public function test_spot_destroy_deletes_spot()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $response = $this->deleteJson("/api/spots/{$spot->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => '具体位置删除成功']);

        $this->assertDatabaseMissing('thing_spots', ['id' => $spot->id]);
    }

    public function test_spot_destroy_returns_403_for_other_user_spot()
    {
        $spot = Spot::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/spots/{$spot->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权删除此具体位置']);
    }

    public function test_spot_destroy_returns_400_when_spot_has_items()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        Item::factory()->create(['spot_id' => $spot->id, 'user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/spots/{$spot->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除已有物品的具体位置']);
    }

    // ==================== Area Rooms Tests ====================

    public function test_area_rooms_returns_rooms_for_area()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $otherArea = Area::factory()->create(['user_id' => $this->user->id]);
        $otherRoom = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $otherArea->id]);

        $response = $this->getJson("/api/areas/{$area->id}/rooms");

        $response->assertStatus(200);
        $rooms = $response->json('data.rooms');
        $this->assertCount(2, $rooms);
        $this->assertContains($room1->id, array_column($rooms, 'id'));
        $this->assertContains($room2->id, array_column($rooms, 'id'));
        $this->assertNotContains($otherRoom->id, array_column($rooms, 'id'));
    }

    public function test_area_rooms_returns_403_for_other_user_area()
    {
        $area = Area::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/areas/{$area->id}/rooms");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此区域的房间']);
    }

    public function test_area_rooms_returns_empty_when_area_has_no_rooms()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/areas/{$area->id}/rooms");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.rooms'));
    }

    // ==================== Room Spots Tests ====================

    public function test_room_spots_returns_spots_for_room()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot1 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $spot2 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $otherRoom = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $otherSpot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $otherRoom->id]);

        $response = $this->getJson("/api/rooms/{$room->id}/spots");

        $response->assertStatus(200);
        $spots = $response->json('data.spots');
        $this->assertCount(2, $spots);
        $this->assertContains($spot1->id, array_column($spots, 'id'));
        $this->assertContains($spot2->id, array_column($spots, 'id'));
        $this->assertNotContains($otherSpot->id, array_column($spots, 'id'));
    }

    public function test_room_spots_returns_403_for_other_user_room()
    {
        $room = Room::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/rooms/{$room->id}/spots");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此房间的位置']);
    }

    public function test_room_spots_returns_empty_when_room_has_no_spots()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        $response = $this->getJson("/api/rooms/{$room->id}/spots");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.spots'));
    }

    // ==================== Location Tree Tests ====================

    public function test_location_tree_returns_tree_structure()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $response = $this->getJson('/api/locations/tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tree' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'original_id',
                            'children',
                            'items_count',
                        ],
                    ],
                    'areas',
                    'rooms',
                    'spots',
                ],
            ])
            ->assertJsonFragment([
                'id' => "area_{$area->id}",
                'name' => $area->name,
                'type' => 'area',
            ])
            ->assertJsonFragment([
                'id' => "room_{$room->id}",
                'name' => $room->name,
                'type' => 'room',
            ])
            ->assertJsonFragment([
                'id' => "spot_{$spot->id}",
                'name' => $spot->name,
                'type' => 'spot',
            ]);
    }

    public function test_location_tree_only_returns_user_data()
    {
        $userArea = Area::factory()->create(['user_id' => $this->user->id]);
        $otherUserArea = Area::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/locations/tree');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => "area_{$userArea->id}"])
            ->assertJsonMissing(['id' => "area_{$otherUserArea->id}"]);
    }

    public function test_location_tree_returns_empty_when_user_has_no_data()
    {
        $response = $this->getJson('/api/locations/tree');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'tree' => [],
                    'areas' => [],
                    'rooms' => [],
                    'spots' => [],
                ],
            ]);
    }

    public function test_location_tree_includes_items_count()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        // Create items for testing items_count
        Item::factory()->create([
            'spot_id' => $spot->id,
            'area_id' => $area->id,
            'room_id' => $room->id,
            'user_id' => $this->user->id,
            'quantity' => 5,
        ]);

        $response = $this->getJson('/api/locations/tree');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => "area_{$area->id}",
                'items_count' => 5,
            ])
            ->assertJsonFragment([
                'id' => "room_{$room->id}",
                'items_count' => 5,
            ])
            ->assertJsonFragment([
                'id' => "spot_{$spot->id}",
                'items_count' => 1,
            ]);
    }

    // ==================== Edge Cases and Additional Tests ====================

    public function test_area_index_includes_rooms_count()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        Room::factory()->count(3)->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        $response = $this->getJson('/api/areas');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $area->id,
                'rooms_count' => 3,
            ]);
    }

    public function test_room_index_includes_spots_count()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        Spot::factory()->count(2)->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $response = $this->getJson('/api/rooms');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $room->id,
                'spots_count' => 2,
            ]);
    }

    public function test_spot_index_includes_items_count()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        Item::factory()->count(3)->create(['spot_id' => $spot->id, 'user_id' => $this->user->id]);

        $response = $this->getJson('/api/spots');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $spot->id,
                'items_count' => 3,
            ]);
    }

    public function test_area_show_includes_rooms_relationship()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        $response = $this->getJson("/api/areas/{$area->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.area.id', $area->id)
            ->assertJsonPath('data.area.name', $area->name)
            ->assertJsonStructure([
                'data' => [
                    'area' => [
                        'id',
                        'name',
                        'rooms' => [
                            '*' => [
                                'id',
                                'name',
                                'area_id',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_room_show_includes_area_and_spots_relationships()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $response = $this->getJson("/api/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.room.id', $room->id)
            ->assertJsonStructure([
                'data' => [
                    'room' => [
                        'id',
                        'name',
                        'area' => [
                            'id',
                            'name',
                        ],
                        'spots' => [
                            '*' => [
                                'id',
                                'name',
                                'room_id',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_spot_show_includes_room_area_and_items_relationships()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);
        $item = Item::factory()->create(['spot_id' => $spot->id, 'user_id' => $this->user->id]);

        $response = $this->getJson("/api/spots/{$spot->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.spot.id', $spot->id)
            ->assertJsonStructure([
                'data' => [
                    'spot' => [
                        'id',
                        'name',
                        'room' => [
                            'id',
                            'name',
                            'area' => [
                                'id',
                                'name',
                            ],
                        ],
                        'items' => [
                            '*' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
