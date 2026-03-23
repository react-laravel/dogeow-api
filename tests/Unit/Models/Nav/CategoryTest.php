<?php

namespace Tests\Unit\Models\Nav;

use App\Models\Nav\Category;
use App\Models\Nav\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_be_created()
    {
        $category = Category::factory()->create([
            'name' => 'Tools',
            'icon' => 'wrench',
            'description' => 'Development tools',
            'sort_order' => 1,
            'is_visible' => true,
        ]);

        $this->assertDatabaseHas('nav_categories', [
            'id' => $category->id,
            'name' => 'Tools',
            'icon' => 'wrench',
            'description' => 'Development tools',
            'sort_order' => 1,
            'is_visible' => true,
        ]);
    }

    public function test_category_has_many_items()
    {
        $category = Category::factory()->create();
        $item1 = Item::factory()->create(['nav_category_id' => $category->id]);
        $item2 = Item::factory()->create(['nav_category_id' => $category->id]);

        $this->assertCount(2, $category->items);
        $this->assertTrue($category->items->contains($item1));
        $this->assertTrue($category->items->contains($item2));
    }

    public function test_category_can_be_soft_deleted()
    {
        $category = Category::factory()->create();

        $category->delete();

        $this->assertSoftDeleted('nav_categories', ['id' => $category->id]);
    }

    public function test_category_fillable_attributes()
    {
        $data = [
            'name' => 'Settings',
            'icon' => 'gear',
            'description' => 'System settings',
            'sort_order' => 5,
            'is_visible' => false,
        ];

        $category = Category::create($data);

        $this->assertEquals($data['name'], $category->name);
        $this->assertEquals($data['icon'], $category->icon);
        $this->assertEquals($data['description'], $category->description);
        $this->assertEquals($data['sort_order'], $category->sort_order);
        $this->assertEquals($data['is_visible'], $category->is_visible);
    }

    public function test_category_casts_is_visible_to_boolean()
    {
        $category = Category::factory()->create(['is_visible' => 1]);

        $this->assertIsBool($category->is_visible);
        $this->assertTrue($category->is_visible);
    }

    public function test_category_casts_sort_order_to_integer()
    {
        $category = Category::factory()->create(['sort_order' => '10']);

        $this->assertIsInt($category->sort_order);
        $this->assertEquals(10, $category->sort_order);
    }

    public function test_category_can_have_icon()
    {
        $category = Category::factory()->create(['icon' => 'star']);

        $this->assertEquals('star', $category->icon);
    }

    public function test_category_can_have_description()
    {
        $category = Category::factory()->create(['description' => 'Test description']);

        $this->assertEquals('Test description', $category->description);
    }

    public function test_category_can_be_hidden()
    {
        $category = Category::factory()->create(['is_visible' => false]);

        $this->assertFalse($category->is_visible);
    }
}
