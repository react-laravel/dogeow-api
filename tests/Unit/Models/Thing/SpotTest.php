<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpotTest extends TestCase
{
    use RefreshDatabase;

    public function test_spot_can_be_created()
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $spot = Spot::factory()->create([
            'name' => 'Desk',
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('thing_spots', [
            'id' => $spot->id,
            'name' => 'Desk',
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_spot_belongs_to_room()
    {
        $room = Room::factory()->create();
        $spot = Spot::factory()->create(['room_id' => $room->id]);

        $this->assertInstanceOf(Room::class, $spot->room);
        $this->assertEquals($room->id, $spot->room->id);
    }

    public function test_spot_belongs_to_user()
    {
        $user = User::factory()->create();
        $spot = Spot::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $spot->user);
        $this->assertEquals($user->id, $spot->user->id);
    }

    public function test_spot_has_many_items()
    {
        $spot = Spot::factory()->create();
        $item1 = Item::factory()->create(['spot_id' => $spot->id]);
        $item2 = Item::factory()->create(['spot_id' => $spot->id]);

        $this->assertCount(2, $spot->items);
        $this->assertTrue($spot->items->contains($item1));
        $this->assertTrue($spot->items->contains($item2));
    }

    public function test_spot_fillable_attributes()
    {
        $data = [
            'name' => 'Shelf',
            'room_id' => Room::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
        ];

        $spot = Spot::create($data);

        $this->assertEquals($data['name'], $spot->name);
        $this->assertEquals($data['room_id'], $spot->room_id);
        $this->assertEquals($data['user_id'], $spot->user_id);
    }
}
