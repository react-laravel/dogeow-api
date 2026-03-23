<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_can_be_created()
    {
        $user = User::factory()->create();
        $area = Area::factory()->create();
        $room = Room::factory()->create([
            'name' => 'Bedroom',
            'area_id' => $area->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('thing_rooms', [
            'id' => $room->id,
            'name' => 'Bedroom',
            'area_id' => $area->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_room_belongs_to_area()
    {
        $area = Area::factory()->create();
        $room = Room::factory()->create(['area_id' => $area->id]);

        $this->assertInstanceOf(Area::class, $room->area);
        $this->assertEquals($area->id, $room->area->id);
    }

    public function test_room_belongs_to_user()
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $room->user);
        $this->assertEquals($user->id, $room->user->id);
    }

    public function test_room_has_many_spots()
    {
        $room = Room::factory()->create();
        $spot1 = Spot::factory()->create(['room_id' => $room->id]);
        $spot2 = Spot::factory()->create(['room_id' => $room->id]);

        $this->assertCount(2, $room->spots);
        $this->assertTrue($room->spots->contains($spot1));
        $this->assertTrue($room->spots->contains($spot2));
    }

    public function test_room_fillable_attributes()
    {
        $data = [
            'name' => 'Kitchen',
            'area_id' => Area::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
        ];

        $room = Room::create($data);

        $this->assertEquals($data['name'], $room->name);
        $this->assertEquals($data['area_id'], $room->area_id);
        $this->assertEquals($data['user_id'], $room->user_id);
    }
}
