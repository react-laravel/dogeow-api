<?php

namespace Tests\Feature\Controllers\Nav;

use App\Models\Nav\Category;
use App\Models\Nav\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * 测试获取所有导航项(默认只显示可见的)
     */
    public function test_index_returns_visible_items()
    {
        $category = Category::factory()->create();
        $visibleItem = Item::factory()->visible()->create(['nav_category_id' => $category->id]);
        $hiddenItem = Item::factory()->hidden()->create(['nav_category_id' => $category->id]);

        $response = $this->getJson('/api/nav/items');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $visibleItem->id])
            ->assertJsonMissing(['id' => $hiddenItem->id]);
    }

    /**
     * 测试获取所有导航项(管理员视图)
     */
    public function test_index_with_show_all_returns_all_items()
    {
        $category = Category::factory()->create();
        $visibleItem = Item::factory()->visible()->create(['nav_category_id' => $category->id]);
        $hiddenItem = Item::factory()->hidden()->create(['nav_category_id' => $category->id]);

        $response = $this->getJson('/api/nav/items?show_all=1');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $visibleItem->id])
            ->assertJsonFragment(['id' => $hiddenItem->id]);
    }

    /**
     * 测试按分类筛选导航项
     */
    public function test_index_filters_by_category()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $item1 = Item::factory()->visible()->create(['nav_category_id' => $category1->id]);
        $item2 = Item::factory()->visible()->create(['nav_category_id' => $category2->id]);

        $response = $this->getJson("/api/nav/items?category_id={$category1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $item1->id])
            ->assertJsonMissing(['id' => $item2->id]);
    }

    /**
     * 测试导航项按排序顺序返回
     */
    public function test_items_are_ordered_by_sort_order()
    {
        $category = Category::factory()->create();

        $item3 = Item::factory()->create(['nav_category_id' => $category->id, 'sort_order' => 3]);
        $item1 = Item::factory()->create(['nav_category_id' => $category->id, 'sort_order' => 1]);
        $item2 = Item::factory()->create(['nav_category_id' => $category->id, 'sort_order' => 2]);

        $response = $this->getJson('/api/nav/items?show_all=1');

        $response->assertStatus(200);

        $items = $response->json();
        $this->assertEquals($item1->id, $items[0]['id']);
        $this->assertEquals($item2->id, $items[1]['id']);
        $this->assertEquals($item3->id, $items[2]['id']);
    }

    /**
     * 测试创建导航项
     */
    public function test_store_creates_new_item()
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();

        $data = [
            'nav_category_id' => $category->id,
            'name' => 'Test Item',
            'url' => 'https://example.com',
            'icon' => 'test-icon',
            'description' => 'Test description',
            'sort_order' => 5,
            'is_visible' => true,
            'is_new_window' => false,
        ];

        $response = $this->postJson('/api/nav/items', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '导航项创建成功',
                'item' => [
                    'nav_category_id' => $category->id,
                    'name' => 'Test Item',
                    'url' => 'https://example.com',
                    'icon' => 'test-icon',
                    'description' => 'Test description',
                    'sort_order' => 5,
                    'is_visible' => true,
                    'is_new_window' => false,
                ],
            ]);

        $this->assertDatabaseHas('nav_items', [
            'nav_category_id' => $category->id,
            'name' => 'Test Item',
            'url' => 'https://example.com',
            'icon' => 'test-icon',
            'description' => 'Test description',
            'sort_order' => 5,
            'is_visible' => true,
            'is_new_window' => false,
        ]);
    }

    /**
     * 测试创建导航项时的验证失败 - 缺少必填字段
     */
    public function test_store_validation_fails_without_required_fields()
    {
        $this->actingAs($this->user);

        $data = [
            'name' => 'Test Item',
            // 缺少 nav_category_id 和 url
        ];

        $response = $this->postJson('/api/nav/items', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nav_category_id', 'url']);
    }

    /**
     * 测试创建导航项时的验证失败 - 分类不存在
     */
    public function test_store_validation_fails_with_nonexistent_category()
    {
        $this->actingAs($this->user);

        $data = [
            'nav_category_id' => 999,
            'name' => 'Test Item',
            'url' => 'https://example.com',
        ];

        $response = $this->postJson('/api/nav/items', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nav_category_id']);
    }

    /**
     * 测试创建导航项时的验证失败 - 名称过长
     */
    public function test_store_validation_fails_with_long_name()
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();

        $data = [
            'nav_category_id' => $category->id,
            'name' => str_repeat('a', 51), // 超过 50 字符限制
            'url' => 'https://example.com',
        ];

        $response = $this->postJson('/api/nav/items', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 测试创建导航项时的验证失败 - URL 过长
     */
    public function test_store_validation_fails_with_long_url()
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();

        $data = [
            'nav_category_id' => $category->id,
            'name' => 'Test Item',
            'url' => str_repeat('a', 256), // 超过 255 字符限制
        ];

        $response = $this->postJson('/api/nav/items', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /**
     * 测试显示指定导航项
     */
    public function test_show_returns_item_with_category()
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['nav_category_id' => $category->id]);

        $response = $this->getJson("/api/nav/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $item->id,
                'name' => $item->name,
                'url' => $item->url,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
            ]);
    }

    /**
     * 测试显示不存在的导航项
     */
    public function test_show_returns_404_for_nonexistent_item()
    {
        $response = $this->getJson('/api/nav/items/999');

        $response->assertStatus(404);
    }

    /**
     * 测试更新导航项
     */
    public function test_update_modifies_item()
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();
        $item = Item::factory()->create(['nav_category_id' => $category->id]);

        $updateData = [
            'nav_category_id' => $category->id,
            'name' => 'Updated Item',
            'url' => 'https://updated.com',
            'icon' => 'updated-icon',
            'description' => 'Updated description',
            'sort_order' => 10,
            'is_visible' => false,
            'is_new_window' => true,
        ];

        $response = $this->putJson("/api/nav/items/{$item->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '导航项更新成功',
                'item' => [
                    'id' => $item->id,
                    'name' => 'Updated Item',
                    'url' => 'https://updated.com',
                    'icon' => 'updated-icon',
                    'description' => 'Updated description',
                    'sort_order' => 10,
                    'is_visible' => false,
                    'is_new_window' => true,
                ],
            ]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $item->id,
            'name' => 'Updated Item',
            'url' => 'https://updated.com',
            'icon' => 'updated-icon',
            'description' => 'Updated description',
            'sort_order' => 10,
            'is_visible' => false,
            'is_new_window' => true,
        ]);
    }

    /**
     * 测试部分更新导航项
     */
    public function test_update_partial_fields()
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();
        $item = Item::factory()->create(['nav_category_id' => $category->id]);

        $updateData = [
            'nav_category_id' => $category->id,
            'name' => 'Updated Name',
            'url' => $item->url, // 保持原有 URL
        ];

        $response = $this->putJson("/api/nav/items/{$item->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'item' => [
                    'id' => $item->id,
                    'name' => 'Updated Name',
                ],
            ]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $item->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * 测试更新导航项时的验证失败
     */
    public function test_update_validation_fails_with_long_name()
    {
        $this->actingAs($this->user);

        $category = Category::factory()->create();
        $item = Item::factory()->create(['nav_category_id' => $category->id]);

        $updateData = [
            'name' => str_repeat('a', 51),
        ];

        $response = $this->putJson("/api/nav/items/{$item->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 测试删除导航项
     */
    public function test_destroy_deletes_item()
    {
        $this->actingAs($this->user);

        $item = Item::factory()->create();

        $response = $this->deleteJson("/api/nav/items/{$item->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '导航项删除成功',
            ]);

        $this->assertSoftDeleted('nav_items', ['id' => $item->id]);
    }

    /**
     * 测试删除不存在的导航项
     */
    public function test_destroy_returns_404_for_nonexistent_item()
    {
        $this->actingAs($this->user);

        $response = $this->deleteJson('/api/nav/items/999');

        $response->assertStatus(404);
    }

    /**
     * 测试记录点击
     */
    public function test_record_click_increments_clicks()
    {
        $item = Item::factory()->create(['clicks' => 5]);

        $response = $this->postJson("/api/nav/items/{$item->id}/click");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '点击记录成功',
                'clicks' => 6,
            ]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $item->id,
            'clicks' => 6,
        ]);
    }

    /**
     * 测试记录点击不存在的导航项
     */
    public function test_record_click_returns_404_for_nonexistent_item()
    {
        $response = $this->postJson('/api/nav/items/999/click');

        $response->assertStatus(404);
    }

    /**
     * 测试记录点击多次递增
     */
    public function test_record_click_increments_multiple_times()
    {
        $item = Item::factory()->create(['clicks' => 0]);

        // 第一次点击
        $response1 = $this->postJson("/api/nav/items/{$item->id}/click");
        $response1->assertStatus(200)->assertJson(['clicks' => 1]);

        // 第二次点击
        $response2 = $this->postJson("/api/nav/items/{$item->id}/click");
        $response2->assertStatus(200)->assertJson(['clicks' => 2]);

        // 第三次点击
        $response3 = $this->postJson("/api/nav/items/{$item->id}/click");
        $response3->assertStatus(200)->assertJson(['clicks' => 3]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $item->id,
            'clicks' => 3,
        ]);
    }

    /**
     * 测试导航项包含分类信息
     */
    public function test_items_include_category_information()
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['nav_category_id' => $category->id]);

        $response = $this->getJson('/api/nav/items?show_all=1');

        $response->assertStatus(200);

        $items = $response->json();
        $this->assertNotEmpty($items);

        $foundItem = collect($items)->firstWhere('id', $item->id);
        $this->assertNotNull($foundItem);
        $this->assertArrayHasKey('category', $foundItem);
        $this->assertEquals($category->id, $foundItem['category']['id']);
        $this->assertEquals($category->name, $foundItem['category']['name']);
    }
}
