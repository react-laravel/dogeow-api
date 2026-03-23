<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_can_be_created()
    {
        $user = User::factory()->create();
        $area = Area::factory()->create([
            'name' => 'Living Room',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('thing_areas', [
            'id' => $area->id,
            'name' => 'Living Room',
            'user_id' => $user->id,
        ]);
    }

    public function test_area_belongs_to_user()
    {
        $user = User::factory()->create();
        $area = Area::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $area->user);
        $this->assertEquals($user->id, $area->user->id);
    }

    public function test_area_has_many_rooms()
    {
        $area = Area::factory()->create();
        $room1 = Room::factory()->create(['area_id' => $area->id]);
        $room2 = Room::factory()->create(['area_id' => $area->id]);

        $this->assertCount(2, $area->rooms);
        $this->assertTrue($area->rooms->contains($room1));
        $this->assertTrue($area->rooms->contains($room2));
    }

    public function test_area_fillable_attributes()
    {
        $data = [
            'name' => 'Kitchen',
            'user_id' => User::factory()->create()->id,
        ];

        $area = Area::create($data);

        $this->assertEquals($data['name'], $area->name);
        $this->assertEquals($data['user_id'], $area->user_id);
    }
}
