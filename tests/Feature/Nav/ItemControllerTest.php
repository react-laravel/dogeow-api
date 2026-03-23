<?php

namespace Tests\Feature\Nav;

use App\Models\Nav\Category;
use App\Models\Nav\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::factory()->create();
        $this->item = Item::factory()->visible()->create([
            'nav_category_id' => $this->category->id,
        ]);
    }

    public function test_index_returns_all_visible_items(): void
    {
        // Create visible items
        $visibleItems = Item::factory()->count(3)->visible()->create([
            'nav_category_id' => $this->category->id,
        ]);

        // Create hidden items (should not be returned by default)
        Item::factory()->count(2)->hidden()->create([
            'nav_category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/nav/items');

        $response->assertStatus(200)
            ->assertJsonCount(4) // 3 visible + 1 from setUp
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'nav_category_id',
                    'name',
                    'url',
                    'icon',
                    'description',
                    'sort_order',
                    'is_visible',
                    'is_new_window',
                    'clicks',
                    'created_at',
                    'updated_at',
                    'category' => [
                        'id',
                        'name',
                        'icon',
                        'description',
                        'sort_order',
                        'is_visible',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // Verify only visible items are returned
        $responseData = $response->json();
        foreach ($responseData as $item) {
            $this->assertTrue($item['is_visible']);
        }
    }

    public function test_index_returns_all_items_when_show_all_parameter(): void
    {
        // Create visible and hidden items
        Item::factory()->count(2)->visible()->create([
            'nav_category_id' => $this->category->id,
        ]);
        Item::factory()->count(2)->hidden()->create([
            'nav_category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/nav/items?show_all=1');

        $response->assertStatus(200)
            ->assertJsonCount(5); // 2 visible + 2 hidden + 1 from setUp
    }

    public function test_index_filters_by_category_id(): void
    {
        $category2 = Category::factory()->create();

        // Create visible items for different categories
        Item::factory()->count(3)->visible()->create([
            'nav_category_id' => $this->category->id,
        ]);
        Item::factory()->count(2)->visible()->create([
            'nav_category_id' => $category2->id,
        ]);

        $response = $this->getJson('/api/nav/items?category_id=' . $this->category->id);

        $response->assertStatus(200)
            ->assertJsonCount(4); // 3 + 1 from setUp

        // Verify all items belong to the specified category
        $responseData = $response->json();
        foreach ($responseData as $item) {
            $this->assertEquals($this->category->id, $item['nav_category_id']);
        }
    }

    public function test_index_orders_by_sort_order(): void
    {
        $this->item->update([
            'sort_order' => 0,
            'is_visible' => true,
        ]);

        // Create items with different sort orders
        Item::factory()->visible()->create([
            'nav_category_id' => $this->category->id,
            'sort_order' => 3,
        ]);
        Item::factory()->visible()->create([
            'nav_category_id' => $this->category->id,
            'sort_order' => 1,
        ]);
        Item::factory()->visible()->create([
            'nav_category_id' => $this->category->id,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/nav/items');

        $response->assertStatus(200);

        $responseData = $response->json();
        $sortOrders = array_column($responseData, 'sort_order');
        $this->assertSame([0, 1, 2, 3], $sortOrders);
    }

    public function test_store_creates_new_item(): void
    {
        $itemData = [
            'nav_category_id' => $this->category->id,
            'name' => 'Test Navigation',
            'url' => 'https://example.com',
            'icon' => 'test-icon',
            'description' => 'Test description',
            'sort_order' => 5,
            'is_visible' => true,
            'is_new_window' => false,
        ];

        $response = $this->postJson('/api/nav/items', $itemData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '导航项创建成功',
                'item' => [
                    'name' => 'Test Navigation',
                    'url' => 'https://example.com',
                    'icon' => 'test-icon',
                    'description' => 'Test description',
                    'sort_order' => 5,
                    'is_visible' => true,
                    'is_new_window' => false,
                    'nav_category_id' => $this->category->id,
                ],
            ]);

        $this->assertDatabaseHas('nav_items', [
            'name' => 'Test Navigation',
            'url' => 'https://example.com',
            'nav_category_id' => $this->category->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/nav/items', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nav_category_id', 'name', 'url']);
    }

    public function test_store_validates_category_exists(): void
    {
        $itemData = [
            'nav_category_id' => 99999, // Non-existent category
            'name' => 'Test Navigation',
            'url' => 'https://example.com',
        ];

        $response = $this->postJson('/api/nav/items', $itemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nav_category_id']);
    }

    public function test_store_validates_field_lengths(): void
    {
        $itemData = [
            'nav_category_id' => $this->category->id,
            'name' => str_repeat('a', 51), // Exceeds max length
            'url' => str_repeat('a', 256), // Exceeds max length
            'icon' => str_repeat('a', 101), // Exceeds max length
        ];

        $response = $this->postJson('/api/nav/items', $itemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'url', 'icon']);
    }

    public function test_show_returns_item_with_category(): void
    {
        $response = $this->getJson('/api/nav/items/' . $this->item->id);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->item->id,
                'name' => $this->item->name,
                'url' => $this->item->url,
                'nav_category_id' => $this->category->id,
                'category' => [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_item(): void
    {
        $response = $this->getJson('/api/nav/items/99999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_item(): void
    {
        $updateData = [
            'nav_category_id' => $this->category->id,
            'name' => 'Updated Navigation',
            'url' => 'https://updated-example.com',
            'icon' => 'updated-icon',
            'description' => 'Updated description',
            'sort_order' => 10,
            'is_visible' => false,
            'is_new_window' => true,
        ];

        $response = $this->putJson('/api/nav/items/' . $this->item->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '导航项更新成功',
                'item' => [
                    'id' => $this->item->id,
                    'name' => 'Updated Navigation',
                    'url' => 'https://updated-example.com',
                    'icon' => 'updated-icon',
                    'description' => 'Updated description',
                    'sort_order' => 10,
                    'is_visible' => false,
                    'is_new_window' => true,
                ],
            ]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $this->item->id,
            'name' => 'Updated Navigation',
            'url' => 'https://updated-example.com',
        ]);
    }

    public function test_update_partial_fields(): void
    {
        $updateData = [
            'nav_category_id' => $this->category->id,
            'name' => 'Partially Updated',
            'url' => 'https://partial-update.com',
        ];

        $response = $this->putJson('/api/nav/items/' . $this->item->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'item' => [
                    'id' => $this->item->id,
                    'name' => 'Partially Updated',
                    'url' => 'https://partial-update.com',
                ],
            ]);

        // Verify other fields remain unchanged
        $this->assertDatabaseHas('nav_items', [
            'id' => $this->item->id,
            'name' => 'Partially Updated',
            'url' => 'https://partial-update.com',
            'icon' => $this->item->icon,
            'description' => $this->item->description,
        ]);
    }

    public function test_update_validates_fields(): void
    {
        $updateData = [
            'nav_category_id' => $this->category->id,
            'name' => str_repeat('a', 51), // Exceeds max length
            'url' => str_repeat('a', 256), // Exceeds max length
        ];

        $response = $this->putJson('/api/nav/items/' . $this->item->id, $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'url']);
    }

    public function test_update_returns_404_for_nonexistent_item(): void
    {
        $updateData = [
            'name' => 'Updated Name',
            'url' => 'https://example.com',
        ];

        $response = $this->putJson('/api/nav/items/99999', $updateData);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_item(): void
    {
        $response = $this->deleteJson('/api/nav/items/' . $this->item->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '导航项删除成功',
            ]);

        // Verify soft delete (item should still exist in database but with deleted_at)
        $this->assertDatabaseHas('nav_items', [
            'id' => $this->item->id,
        ]);

        // Verify the item is soft deleted (has deleted_at timestamp)
        $deletedItem = Item::withTrashed()->find($this->item->id);
        $this->assertNotNull($deletedItem->deleted_at);
    }

    public function test_destroy_returns_404_for_nonexistent_item(): void
    {
        $response = $this->deleteJson('/api/nav/items/99999');

        $response->assertStatus(404);
    }

    public function test_record_click_increments_clicks(): void
    {
        $initialClicks = $this->item->clicks;

        $response = $this->postJson('/api/nav/items/' . $this->item->id . '/click');

        $response->assertStatus(200)
            ->assertJson([
                'message' => '点击记录成功',
                'clicks' => $initialClicks + 1,
            ]);

        $this->assertDatabaseHas('nav_items', [
            'id' => $this->item->id,
            'clicks' => $initialClicks + 1,
        ]);
    }

    public function test_record_click_returns_404_for_nonexistent_item(): void
    {
        $response = $this->postJson('/api/nav/items/99999/click');

        $response->assertStatus(404);
    }

    public function test_record_click_multiple_times(): void
    {
        $initialClicks = $this->item->clicks;

        // Record multiple clicks
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/nav/items/' . $this->item->id . '/click');
            $response->assertStatus(200);
        }

        $this->assertDatabaseHas('nav_items', [
            'id' => $this->item->id,
            'clicks' => $initialClicks + 3,
        ]);
    }
}
