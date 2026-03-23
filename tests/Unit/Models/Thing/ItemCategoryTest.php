<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_category_can_be_created()
    {
        $category = ItemCategory::factory()->create([
            'name' => 'Electronics',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertDatabaseHas('thing_item_categories', [
            'id' => $category->id,
            'name' => 'Electronics',
        ]);
    }

    public function test_item_category_belongs_to_user()
    {
        $user = User::factory()->create();
        $category = ItemCategory::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $category->user);
        $this->assertEquals($user->id, $category->user->id);
    }

    public function test_item_category_has_many_items()
    {
        $category = ItemCategory::factory()->create();
        $item1 = Item::factory()->create(['category_id' => $category->id]);
        $item2 = Item::factory()->create(['category_id' => $category->id]);

        $this->assertCount(2, $category->items);
        $this->assertInstanceOf(Item::class, $category->items->first());
    }

    public function test_item_category_belongs_to_parent()
    {
        $parentCategory = ItemCategory::factory()->create();
        $childCategory = ItemCategory::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertInstanceOf(ItemCategory::class, $childCategory->parent);
        $this->assertEquals($parentCategory->id, $childCategory->parent->id);
    }

    public function test_item_category_has_many_children()
    {
        $parentCategory = ItemCategory::factory()->create();
        $child1 = ItemCategory::factory()->create(['parent_id' => $parentCategory->id]);
        $child2 = ItemCategory::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertCount(2, $parentCategory->children);
        $this->assertInstanceOf(ItemCategory::class, $parentCategory->children->first());
    }

    public function test_item_category_is_parent()
    {
        $parentCategory = ItemCategory::factory()->create(['parent_id' => null]);
        $childCategory = ItemCategory::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertTrue($parentCategory->isParent());
        $this->assertFalse($childCategory->isParent());
    }

    public function test_item_category_is_child()
    {
        $parentCategory = ItemCategory::factory()->create(['parent_id' => null]);
        $childCategory = ItemCategory::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertFalse($parentCategory->isChild());
        $this->assertTrue($childCategory->isChild());
    }

    public function test_item_category_fillable_attributes()
    {
        $data = [
            'name' => 'Test Category',
            'user_id' => User::factory()->create()->id,
            'parent_id' => null,
        ];

        $category = ItemCategory::create($data);

        $this->assertEquals($data['name'], $category->name);
        $this->assertEquals($data['user_id'], $category->user_id);
        $this->assertEquals($data['parent_id'], $category->parent_id);
    }

    public function test_item_category_factory_states()
    {
        // 测试主分类状态
        $parentCategory = ItemCategory::factory()->parent()->create();
        $this->assertNull($parentCategory->parent_id);
        $this->assertTrue($parentCategory->isParent());

        // 测试子分类状态
        $childCategory = ItemCategory::factory()->child($parentCategory)->create();
        $this->assertEquals($parentCategory->id, $childCategory->parent_id);
        $this->assertTrue($childCategory->isChild());
    }

    public function test_item_category_hierarchy()
    {
        // 创建三级分类结构
        $grandParent = ItemCategory::factory()->create(['parent_id' => null]);
        $parent = ItemCategory::factory()->create(['parent_id' => $grandParent->id]);
        $child = ItemCategory::factory()->create(['parent_id' => $parent->id]);

        // 测试父分类关系
        $this->assertEquals($grandParent->id, $parent->parent->id);
        $this->assertEquals($parent->id, $child->parent->id);

        // 测试子分类关系
        $this->assertCount(1, $grandParent->children);
        $this->assertCount(1, $parent->children);
        $this->assertCount(0, $child->children);

        // 测试层级判断
        $this->assertTrue($grandParent->isParent());
        $this->assertFalse($parent->isParent());
        $this->assertFalse($child->isParent());

        $this->assertFalse($grandParent->isChild());
        $this->assertTrue($parent->isChild());
        $this->assertTrue($child->isChild());
    }

    public function test_item_category_with_items()
    {
        $category = ItemCategory::factory()->create();
        $item1 = Item::factory()->create(['category_id' => $category->id]);
        $item2 = Item::factory()->create(['category_id' => $category->id]);

        $this->assertCount(2, $category->items);
        $this->assertTrue($category->items->contains($item1));
        $this->assertTrue($category->items->contains($item2));
    }

    public function test_item_category_without_items()
    {
        $category = ItemCategory::factory()->create();

        $this->assertCount(0, $category->items);
    }

    public function test_item_category_without_parent()
    {
        $category = ItemCategory::factory()->create(['parent_id' => null]);

        $this->assertNull($category->parent);
        $this->assertTrue($category->isParent());
        $this->assertFalse($category->isChild());
    }

    public function test_item_category_without_children()
    {
        $category = ItemCategory::factory()->create();

        $this->assertCount(0, $category->children);
    }

    public function test_item_category_can_have_null_parent_id()
    {
        $category = ItemCategory::factory()->create(['parent_id' => null]);

        $this->assertDatabaseHas('thing_item_categories', [
            'id' => $category->id,
            'parent_id' => null,
        ]);
    }

    public function test_item_category_can_have_parent_id()
    {
        $parent = ItemCategory::factory()->create();
        $child = ItemCategory::factory()->create(['parent_id' => $parent->id]);

        $this->assertDatabaseHas('thing_item_categories', [
            'id' => $child->id,
            'parent_id' => $parent->id,
        ]);
    }

    public function test_item_category_relationships_work_correctly()
    {
        $user = User::factory()->create();
        $parent = ItemCategory::factory()->create(['user_id' => $user->id]);
        $child = ItemCategory::factory()->create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
        ]);
        $item = Item::factory()->create(['category_id' => $child->id]);

        // 测试用户关系
        $this->assertEquals($user->id, $child->user->id);
        $this->assertEquals($user->id, $parent->user->id);

        // 测试父子关系
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));

        // 测试物品关系
        $this->assertTrue($child->items->contains($item));
        $this->assertEquals($child->id, $item->category->id);
    }
}
