<?php

namespace Tests\Feature\Controllers\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $unauthenticatedTests = [
            'test_unauthenticated_user_cannot_access_categories',
            'test_unauthenticated_user_cannot_create_category',
            'test_unauthenticated_user_cannot_update_category',
            'test_unauthenticated_user_cannot_delete_category',
        ];
        if (! in_array($this->name(), $unauthenticatedTests, true)) {
            Sanctum::actingAs($this->user);
        }
    }

    // ==================== Index Tests ====================

    public function test_index_returns_user_categories()
    {
        $userCategory = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $otherUserCategory = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userCategory->id])
            ->assertJsonMissing(['id' => $otherUserCategory->id]);
    }

    public function test_index_includes_parent_and_children_relationships()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id,
        ]);

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'parent',
                    'children',
                    'items_count',
                ],
            ]);
    }

    public function test_index_returns_empty_array_when_no_categories()
    {
        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    public function test_index_orders_by_parent_id_then_name()
    {
        $categoryB = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'B Category',
            'parent_id' => null,
        ]);
        $categoryA = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'A Category',
            'parent_id' => null,
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Child Category',
            'parent_id' => $categoryA->id,
        ]);

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200);
        $categories = $response->json();

        // Should be ordered by parent_id (null first), then by name
        $this->assertEquals($categoryA->id, $categories[0]['id']);
        $this->assertEquals($categoryB->id, $categories[1]['id']);
        $this->assertEquals($childCategory->id, $categories[2]['id']);
    }

    // ==================== Store Tests ====================

    public function test_store_creates_new_category()
    {
        $data = ['name' => 'Test Category'];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '分类创建成功',
                'category' => [
                    'name' => 'Test Category',
                    'user_id' => $this->user->id,
                ],
            ]);

        $this->assertDatabaseHas('thing_item_categories', [
            'name' => 'Test Category',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_creates_category_with_parent()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);

        $data = [
            'name' => 'Child Category',
            'parent_id' => $parentCategory->id,
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '分类创建成功',
                'category' => [
                    'name' => 'Child Category',
                    'parent_id' => $parentCategory->id,
                    'user_id' => $this->user->id,
                ],
            ]);
    }

    public function test_store_returns_422_for_invalid_parent()
    {
        $data = [
            'name' => 'Test Category',
            'parent_id' => 999,
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_store_returns_400_for_other_user_parent()
    {
        $otherUserParent = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $data = [
            'name' => 'Test Category',
            'parent_id' => $otherUserParent->id,
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(400)
            ->assertJson(['message' => '指定的父分类不存在或无权访问']);
    }

    public function test_store_returns_400_for_third_level_category()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id,
        ]);

        $data = [
            'name' => 'Third Level Category',
            'parent_id' => $childCategory->id,
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(400)
            ->assertJson(['message' => '不能在子分类下创建分类']);
    }

    public function test_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/things/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_name()
    {
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_empty_name()
    {
        $data = ['name' => ''];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_non_string_name()
    {
        $data = ['name' => 123];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_non_integer_parent_id()
    {
        $data = [
            'name' => 'Test Category',
            'parent_id' => 'not-an-integer',
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_store_creates_category_with_null_parent_id()
    {
        $data = [
            'name' => 'Test Category',
            'parent_id' => null,
        ];

        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('thing_item_categories', [
            'name' => 'Test Category',
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);
    }

    // ==================== Show Tests ====================

    public function test_show_returns_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $category->id,
                'name' => $category->name,
            ]);
    }

    public function test_show_returns_403_for_other_user_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权查看此分类']);
    }

    public function test_show_includes_items_relationship()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'items' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_category()
    {
        $response = $this->getJson('/api/things/categories/999');

        $response->assertStatus(404);
    }

    public function test_show_returns_empty_items_array_when_no_items()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'items',
            ]);

        $this->assertEmpty($response->json('items'));
    }

    // ==================== Update Tests ====================

    public function test_update_modifies_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '分类更新成功',
                'category' => [
                    'id' => $category->id,
                    'name' => 'Updated Category',
                ],
            ]);

        $this->assertDatabaseHas('thing_item_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    }

    public function test_update_returns_403_for_other_user_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => '无权更新此分类']);
    }

    public function test_update_validation_fails_with_long_name()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => str_repeat('a', 256)];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_returns_404_for_nonexistent_category()
    {
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson('/api/things/categories/999', $data);

        $response->assertStatus(404);
    }

    public function test_update_validation_fails_without_name()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/things/categories/{$category->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_empty_name()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => ''];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_non_string_name()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 123];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_invalid_parent_id()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = [
            'name' => 'Updated Category',
            'parent_id' => 999,
        ];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_deletes_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => '分类删除成功']);

        $this->assertDatabaseMissing('thing_item_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_destroy_returns_403_for_other_user_category()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => '无权删除此分类']);
    }

    public function test_destroy_returns_400_when_category_has_items()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除已有物品的分类']);
    }

    public function test_destroy_returns_400_when_category_has_children()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);
        $childCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id,
        ]);

        $response = $this->deleteJson("/api/things/categories/{$parentCategory->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除有子分类的分类']);
    }

    public function test_destroy_returns_404_for_nonexistent_category()
    {
        $response = $this->deleteJson('/api/things/categories/999');

        $response->assertStatus(404);
    }

    public function test_destroy_can_delete_category_with_multiple_items()
    {
        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        // Create multiple items
        Item::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
        ]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除已有物品的分类']);
    }

    public function test_destroy_can_delete_category_with_multiple_children()
    {
        $parentCategory = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
            'parent_id' => null,
        ]);

        // Create multiple children
        ItemCategory::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'parent_id' => $parentCategory->id,
        ]);

        $response = $this->deleteJson("/api/things/categories/{$parentCategory->id}");

        $response->assertStatus(400)
            ->assertJson(['message' => '无法删除有子分类的分类']);
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_categories()
    {
        Auth::forgetGuards();

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_category()
    {
        Auth::forgetGuards();

        $data = ['name' => 'Test Category'];
        $response = $this->postJson('/api/things/categories', $data);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_update_category()
    {
        Auth::forgetGuards();

        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson("/api/things/categories/{$category->id}", $data);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_delete_category()
    {
        Auth::forgetGuards();

        $category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/things/categories/{$category->id}");

        $response->assertStatus(401);
    }

    public function test_index_parent_category_items_count_includes_children(): void
    {
        $parent = ItemCategory::factory()->create(['user_id' => $this->user->id, 'parent_id' => null]);
        $child = ItemCategory::factory()->create(['user_id' => $this->user->id, 'parent_id' => $parent->id]);
        Item::factory()->create(['user_id' => $this->user->id, 'category_id' => $parent->id]);
        Item::factory()->count(2)->create(['user_id' => $this->user->id, 'category_id' => $child->id]);

        $response = $this->getJson('/api/things/categories');

        $response->assertStatus(200);
        $categories = $response->json();
        $parentInResponse = collect($categories)->firstWhere('id', $parent->id);
        $childInResponse = collect($categories)->firstWhere('id', $child->id);
        $this->assertNotNull($parentInResponse);
        $this->assertNotNull($childInResponse);
        $this->assertGreaterThanOrEqual(1, $parentInResponse['items_count']);
        $this->assertEquals(2, $childInResponse['items_count']);
    }
}
