<?php

namespace Tests\Unit\Services;

use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use App\Services\Location\LocationTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTreeServiceTest extends TestCase
{
    use RefreshDatabase;

    private LocationTreeService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationTreeService;
        $this->user = User::factory()->create();
    }

    public function test_build_location_tree_returns_correct_structure()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $result = $this->service->buildLocationTree($this->user->id);

        $this->assertArrayHasKey('tree', $result);
        $this->assertArrayHasKey('areas', $result);
        $this->assertArrayHasKey('rooms', $result);
        $this->assertArrayHasKey('spots', $result);

        $this->assertCount(1, $result['tree']);
        $this->assertCount(1, $result['areas']);
        $this->assertCount(1, $result['rooms']);
        $this->assertCount(1, $result['spots']);
    }

    public function test_build_location_tree_includes_correct_tree_structure()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        $result = $this->service->buildLocationTree($this->user->id);

        $areaNode = $result['tree'][0];
        $this->assertEquals("area_{$area->id}", $areaNode['id']);
        $this->assertEquals($area->name, $areaNode['name']);
        $this->assertEquals('area', $areaNode['type']);
        $this->assertEquals($area->id, $areaNode['original_id']);

        $roomNode = $areaNode['children'][0];
        $this->assertEquals("room_{$room->id}", $roomNode['id']);
        $this->assertEquals($room->name, $roomNode['name']);
        $this->assertEquals('room', $roomNode['type']);
        $this->assertEquals($room->id, $roomNode['original_id']);

        $spotNode = $roomNode['children'][0];
        $this->assertEquals("spot_{$spot->id}", $spotNode['id']);
        $this->assertEquals($spot->name, $spotNode['name']);
        $this->assertEquals('spot', $spotNode['type']);
        $this->assertEquals($spot->id, $spotNode['original_id']);
    }

    public function test_build_location_tree_includes_items_count()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room->id]);

        Item::factory()->create([
            'user_id' => $this->user->id,
            'area_id' => $area->id,
            'room_id' => $room->id,
            'spot_id' => $spot->id,
            'quantity' => 5,
        ]);

        $result = $this->service->buildLocationTree($this->user->id);

        $areaNode = $result['tree'][0];
        $this->assertEquals(5, $areaNode['items_count']);

        $roomNode = $areaNode['children'][0];
        $this->assertEquals(5, $roomNode['items_count']);

        $spotNode = $roomNode['children'][0];
        $this->assertEquals(1, $spotNode['items_count']); // Spot uses withCount which counts records, not quantity
    }

    public function test_build_location_tree_only_includes_user_data()
    {
        $otherUser = User::factory()->create();

        $userArea = Area::factory()->create(['user_id' => $this->user->id]);
        $otherUserArea = Area::factory()->create(['user_id' => $otherUser->id]);

        $result = $this->service->buildLocationTree($this->user->id);

        $areaIds = collect($result['areas'])->pluck('id')->toArray();
        $this->assertContains($userArea->id, $areaIds);
        $this->assertNotContains($otherUserArea->id, $areaIds);
    }

    public function test_build_location_tree_returns_empty_when_no_data()
    {
        $result = $this->service->buildLocationTree($this->user->id);

        $this->assertEmpty($result['tree']);
        $this->assertEmpty($result['areas']);
        $this->assertEmpty($result['rooms']);
        $this->assertEmpty($result['spots']);
    }

    public function test_build_location_tree_handles_multiple_areas()
    {
        $area1 = Area::factory()->create(['user_id' => $this->user->id]);
        $area2 = Area::factory()->create(['user_id' => $this->user->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area1->id]);
        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area2->id]);

        $result = $this->service->buildLocationTree($this->user->id);

        $this->assertCount(2, $result['tree']);
        $this->assertCount(2, $result['areas']);
        $this->assertCount(2, $result['rooms']);
    }

    public function test_build_location_tree_handles_nested_structure()
    {
        $area = Area::factory()->create(['user_id' => $this->user->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);
        $spot1 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room1->id]);
        $spot2 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room1->id]);
        $spot3 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room2->id]);

        $result = $this->service->buildLocationTree($this->user->id);

        $areaNode = $result['tree'][0];
        $this->assertCount(2, $areaNode['children']); // 2 rooms

        $room1Node = $areaNode['children'][0];
        $this->assertCount(2, $room1Node['children']); // 2 spots

        $room2Node = $areaNode['children'][1];
        $this->assertCount(1, $room2Node['children']); // 1 spot
    }

    public function test_build_location_tree_orders_nodes_by_id_consistently()
    {
        $area1 = Area::factory()->create(['user_id' => $this->user->id]);
        $area2 = Area::factory()->create(['user_id' => $this->user->id]);

        $room2 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area2->id]);
        $room1 = Room::factory()->create(['user_id' => $this->user->id, 'area_id' => $area1->id]);

        $spot2 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room2->id]);
        $spot1 = Spot::factory()->create(['user_id' => $this->user->id, 'room_id' => $room1->id]);

        $result = $this->service->buildLocationTree($this->user->id);

        $this->assertSame([$area1->id, $area2->id], array_column($result['tree'], 'original_id'));
        $this->assertSame([$room1->id], array_column($result['tree'][0]['children'], 'original_id'));
        $this->assertSame([$room2->id], array_column($result['tree'][1]['children'], 'original_id'));
        $this->assertSame([$spot1->id], array_column($result['tree'][0]['children'][0]['children'], 'original_id'));
        $this->assertSame([$spot2->id], array_column($result['tree'][1]['children'][0]['children'], 'original_id'));
    }
}
