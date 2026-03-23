<?php

namespace Tests\Feature;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCategorySearchTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试用户
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_parent_category_search_includes_child_category_items()
    {
        // 创建父分类
        $parentCategory = ItemCategory::create([
            'name' => '电子产品',
            'user_id' => $this->user->id,
        ]);

        // 创建子分类
        $childCategory1 = ItemCategory::create([
            'name' => '手机',
            'parent_id' => $parentCategory->id,
            'user_id' => $this->user->id,
        ]);

        $childCategory2 = ItemCategory::create([
            'name' => '电脑',
            'parent_id' => $parentCategory->id,
            'user_id' => $this->user->id,
        ]);

        // 创建物品：直接属于父分类
        $parentItem = Item::create([
            'name' => '充电器',
            'description' => '通用充电器',
            'user_id' => $this->user->id,
            'category_id' => $parentCategory->id,
            'is_public' => true,
        ]);

        // 创建物品：属于子分类 1
        $childItem1 = Item::create([
            'name' => 'iPhone',
            'description' => '苹果手机',
            'user_id' => $this->user->id,
            'category_id' => $childCategory1->id,
            'is_public' => true,
        ]);

        // 创建物品：属于子分类 2
        $childItem2 = Item::create([
            'name' => 'MacBook',
            'description' => '苹果电脑',
            'user_id' => $this->user->id,
            'category_id' => $childCategory2->id,
            'is_public' => true,
        ]);

        // 创建不相关的分类和物品
        $otherCategory = ItemCategory::create([
            'name' => '家具',
            'user_id' => $this->user->id,
        ]);

        $otherItem = Item::create([
            'name' => '椅子',
            'description' => '办公椅',
            'user_id' => $this->user->id,
            'category_id' => $otherCategory->id,
            'is_public' => true,
        ]);

        // 测试：搜索父分类应该返回父分类及所有子分类的物品
        $response = $this->getJson("/api/things/items?filter[category_id]={$parentCategory->id}");

        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertCount(3, $items, '搜索父分类应该返回 3 个物品(1 个父分类物品 + 2 个子分类物品)');

        // 验证返回的物品 ID
        $itemIds = collect($items)->pluck('id')->toArray();
        $this->assertContains($parentItem->id, $itemIds, '应该包含父分类的物品');
        $this->assertContains($childItem1->id, $itemIds, '应该包含子分类 1 的物品');
        $this->assertContains($childItem2->id, $itemIds, '应该包含子分类 2 的物品');
        $this->assertNotContains($otherItem->id, $itemIds, '不应该包含其他分类的物品');
    }

    public function test_child_category_search_only_includes_child_items()
    {
        // 创建父分类
        $parentCategory = ItemCategory::create([
            'name' => '电子产品',
            'user_id' => $this->user->id,
        ]);

        // 创建子分类
        $childCategory = ItemCategory::create([
            'name' => '手机',
            'parent_id' => $parentCategory->id,
            'user_id' => $this->user->id,
        ]);

        // 创建物品：直接属于父分类
        $parentItem = Item::create([
            'name' => '充电器',
            'description' => '通用充电器',
            'user_id' => $this->user->id,
            'category_id' => $parentCategory->id,
            'is_public' => true,
        ]);

        // 创建物品：属于子分类
        $childItem = Item::create([
            'name' => 'iPhone',
            'description' => '苹果手机',
            'user_id' => $this->user->id,
            'category_id' => $childCategory->id,
            'is_public' => true,
        ]);

        // 测试：搜索子分类应该只返回该子分类的物品
        $response = $this->getJson("/api/things/items?filter[category_id]={$childCategory->id}");

        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertCount(1, $items, '搜索子分类应该只返回 1 个物品');

        // 验证返回的物品 ID
        $itemIds = collect($items)->pluck('id')->toArray();
        $this->assertContains($childItem->id, $itemIds, '应该包含子分类的物品');
        $this->assertNotContains($parentItem->id, $itemIds, '不应该包含父分类的物品');
    }

    public function test_nonexistent_category_returns_empty_result()
    {
        // 测试：搜索不存在的分类 ID
        $response = $this->getJson('/api/things/items?filter[category_id]=99999');

        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertCount(0, $items, '搜索不存在的分类应该返回空结果');
    }

    public function test_uncategorized_filter_returns_items_without_category()
    {
        // 创建分类
        $category = ItemCategory::create([
            'name' => '电子产品',
            'user_id' => $this->user->id,
        ]);

        // 创建有分类的物品
        $categorizedItem = Item::create([
            'name' => '手机',
            'description' => '智能手机',
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'is_public' => true,
        ]);

        // 创建无分类的物品
        $uncategorizedItem = Item::create([
            'name' => '未分类物品',
            'description' => '没有分类的物品',
            'user_id' => $this->user->id,
            'category_id' => null,
            'is_public' => true,
        ]);

        // 测试：搜索未分类应该只返回没有分类的物品
        $response = $this->getJson('/api/things/items?filter[category_id]=uncategorized');

        $response->assertStatus(200);

        $items = $response->json('data');
        $this->assertCount(1, $items, '搜索未分类应该返回 1 个物品');

        // 验证返回的物品 ID
        $itemIds = collect($items)->pluck('id')->toArray();
        $this->assertContains($uncategorizedItem->id, $itemIds, '应该包含未分类的物品');
        $this->assertNotContains($categorizedItem->id, $itemIds, '不应该包含有分类的物品');
    }
}
