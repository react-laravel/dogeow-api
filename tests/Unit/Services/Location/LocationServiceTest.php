<?php

namespace Tests\Unit\Services\Location;

use App\Models\User;
use App\Services\Location\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationService;
    }

    public function test_build_location_tree_returns_tree_structure(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_includes_areas(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_includes_rooms(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_includes_spots(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_calculates_area_item_counts(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_calculates_room_item_counts(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_returns_empty_for_user_with_no_locations(): void
    {
        $user = User::factory()->create();

        $result = $this->service->buildLocationTree($user->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result['tree']);
        $this->assertEmpty($result['areas']);
        $this->assertEmpty($result['rooms']);
        $this->assertEmpty($result['spots']);
    }

    public function test_build_location_tree_nests_rooms_under_areas(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_nests_spots_under_rooms(): void
    {
        // TODO: Implement test
    }

    public function test_build_location_tree_excludes_other_users_locations(): void
    {
        // TODO: Implement test
    }
}
