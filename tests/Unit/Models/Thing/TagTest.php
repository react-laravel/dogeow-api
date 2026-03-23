<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_can_be_created()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create([
            'user_id' => $user->id,
            'name' => 'Electronics',
            'color' => '#FF0000',
        ]);

        $this->assertDatabaseHas('thing_tags', [
            'id' => $tag->id,
            'user_id' => $user->id,
            'name' => 'Electronics',
            'color' => '#FF0000',
        ]);
    }

    public function test_tag_belongs_to_user()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $tag->user);
        $this->assertEquals($user->id, $tag->user->id);
    }

    public function test_tag_can_have_items()
    {
        $tag = Tag::factory()->create();
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        $tag->items()->attach([$item1->id, $item2->id]);

        $this->assertCount(2, $tag->items);
        $this->assertTrue($tag->items->contains($item1));
        $this->assertTrue($tag->items->contains($item2));
    }

    public function test_tag_can_be_soft_deleted()
    {
        $tag = Tag::factory()->create();

        $tag->delete();

        $this->assertSoftDeleted('thing_tags', ['id' => $tag->id]);
    }

    public function test_tag_fillable_attributes()
    {
        $data = [
            'user_id' => User::factory()->create()->id,
            'name' => 'Books',
            'color' => '#00FF00',
        ];

        $tag = Tag::create($data);

        $this->assertEquals($data['name'], $tag->name);
        $this->assertEquals($data['color'], $tag->color);
        $this->assertEquals($data['user_id'], $tag->user_id);
    }

    public function test_tag_has_dates_casted()
    {
        $tag = Tag::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $tag->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $tag->updated_at);
    }

    public function test_tag_can_have_color()
    {
        $tag = Tag::factory()->create(['color' => '#0000FF']);

        $this->assertEquals('#0000FF', $tag->color);
    }

    public function test_tag_can_have_name()
    {
        $tag = Tag::factory()->create(['name' => 'Important']);

        $this->assertEquals('Important', $tag->name);
    }
}
