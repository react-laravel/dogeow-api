<?php

namespace Tests\Unit\Models\Nav;

use App\Models\Nav\Category;
use App\Models\Nav\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_can_be_created()
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create([
            'nav_category_id' => $category->id,
            'name' => 'Dashboard',
            'url' => '/dashboard',
            'icon' => 'dashboard',
            'description' => 'Main dashboard',
            'sort_order' => 1,
            'is_visible' => true,
            'is_new_window' => false,
            'clicks' => 0,
        ]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $item->id,
            'nav_category_id' => $category->id,
            'name' => 'Dashboard',
            'url' => '/dashboard',
            'icon' => 'dashboard',
            'description' => 'Main dashboard',
            'sort_order' => 1,
            'is_visible' => true,
            'is_new_window' => false,
            'clicks' => 0,
        ]);
    }

    public function test_item_belongs_to_category()
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['nav_category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $item->category);
        $this->assertEquals($category->id, $item->category->id);
    }

    public function test_item_can_be_soft_deleted()
    {
        $item = Item::factory()->create();

        $item->delete();

        $this->assertSoftDeleted('nav_items', ['id' => $item->id]);
    }

    public function test_item_fillable_attributes()
    {
        $data = [
            'nav_category_id' => Category::factory()->create()->id,
            'name' => 'Settings',
            'url' => '/settings',
            'icon' => 'gear',
            'description' => 'System settings',
            'sort_order' => 5,
            'is_visible' => false,
            'is_new_window' => true,
            'clicks' => 10,
        ];

        $item = Item::create($data);

        $this->assertEquals($data['name'], $item->name);
        $this->assertEquals($data['url'], $item->url);
        $this->assertEquals($data['icon'], $item->icon);
        $this->assertEquals($data['description'], $item->description);
        $this->assertEquals($data['sort_order'], $item->sort_order);
        $this->assertEquals($data['is_visible'], $item->is_visible);
        $this->assertEquals($data['is_new_window'], $item->is_new_window);
        $this->assertEquals($data['clicks'], $item->clicks);
    }

    public function test_item_casts_is_visible_to_boolean()
    {
        $item = Item::factory()->create(['is_visible' => 1]);

        $this->assertIsBool($item->is_visible);
        $this->assertTrue($item->is_visible);
    }

    public function test_item_casts_is_new_window_to_boolean()
    {
        $item = Item::factory()->create(['is_new_window' => 1]);

        $this->assertIsBool($item->is_new_window);
        $this->assertTrue($item->is_new_window);
    }

    public function test_item_casts_sort_order_to_integer()
    {
        $item = Item::factory()->create(['sort_order' => '10']);

        $this->assertIsInt($item->sort_order);
        $this->assertEquals(10, $item->sort_order);
    }

    public function test_item_casts_clicks_to_integer()
    {
        $item = Item::factory()->create(['clicks' => '5']);

        $this->assertIsInt($item->clicks);
        $this->assertEquals(5, $item->clicks);
    }

    public function test_item_can_have_url()
    {
        $item = Item::factory()->create(['url' => 'https://example.com']);

        $this->assertEquals('https://example.com', $item->url);
    }

    public function test_item_can_have_icon()
    {
        $item = Item::factory()->create(['icon' => 'star']);

        $this->assertEquals('star', $item->icon);
    }

    public function test_item_can_have_description()
    {
        $item = Item::factory()->create(['description' => 'Test description']);

        $this->assertEquals('Test description', $item->description);
    }

    public function test_item_can_be_hidden()
    {
        $item = Item::factory()->create(['is_visible' => false]);

        $this->assertFalse($item->is_visible);
    }

    public function test_item_can_open_in_new_window()
    {
        $item = Item::factory()->create(['is_new_window' => true]);

        $this->assertTrue($item->is_new_window);
    }

    public function test_item_increment_clicks()
    {
        $item = Item::factory()->create(['clicks' => 5]);

        $item->incrementClicks();

        $this->assertEquals(6, $item->fresh()->clicks);
    }
}
