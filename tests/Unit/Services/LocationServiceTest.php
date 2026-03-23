<?php

namespace Tests\Unit\Services;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Services\Location\LocationService;
use Tests\TestCase;

class LocationServiceTest extends TestCase
{
    private LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationService;
    }

    public function test_build_location_tree_returns_structure(): void
    {
        $userId = 1;

        $area = Area::factory()->create(['user_id' => $userId]);
        $room = Room::factory()->create(['user_id' => $userId, 'area_id' => $area->id]);
        $spot = Spot::factory()->create(['user_id' => $userId, 'room_id' => $room->id]);

        $result = $this->service->buildLocationTree($userId);

        $this->assertArrayHasKey('tree', $result);
        $this->assertArrayHasKey('areas', $result);
        $this->assertArrayHasKey('rooms', $result);
        $this->assertArrayHasKey('spots', $result);
    }

    public function test_build_location_tree_returns_empty_for_no_data(): void
    {
        $result = $this->service->buildLocationTree(99999);

        $this->assertEmpty($result['tree']);
        $this->assertEmpty($result['areas']);
        $this->assertEmpty($result['rooms']);
        $this->assertEmpty($result['spots']);
    }

    public function test_build_location_tree_includes_nested_structure(): void
    {
        $userId = 1;

        $area = Area::factory()->create(['user_id' => $userId, 'name' => 'Test Area']);
        $room = Room::factory()->create(['user_id' => $userId, 'area_id' => $area->id, 'name' => 'Test Room']);
        Spot::factory()->create(['user_id' => $userId, 'room_id' => $room->id, 'name' => 'Test Spot']);

        $result = $this->service->buildLocationTree($userId);

        $this->assertCount(1, $result['tree']);
        $this->assertEquals('area_' . $area->id, $result['tree'][0]['id']);
        $this->assertEquals('Test Area', $result['tree'][0]['name']);
        $this->assertEquals('area', $result['tree'][0]['type']);
        $this->assertCount(1, $result['tree'][0]['children']);
    }
}
