<?php

namespace Tests\Feature\Controllers\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\ItemImage;
use App\Models\Thing\Spot;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->category = ItemCategory::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_paginated_items()
    {
        Item::factory()->count(15)->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/things/items');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
        ]);
    }

    public function test_index_only_shows_public_items_for_guest()
    {
        // 创建公开和私有物品
        Item::factory()->create(['is_public' => true]);
        Item::factory()->create(['is_public' => false, 'user_id' => $this->user->id]);

        // 以访客身份访问(API 需要认证，访客会得到 401)
        Auth::forgetGuards();
        $response = $this->getJson('/api/things/items');

        $response->assertStatus(401);
    }

    public function test_index_shows_user_own_items_and_public_items()
    {
        // 创建不同用户的物品
        Item::factory()->create(['is_public' => true]);
        Item::factory()->create(['is_public' => false, 'user_id' => $this->user->id]);
        Item::factory()->create(['is_public' => false, 'user_id' => User::factory()->create()->id]);

        $response = $this->getJson('/api/things/items');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data')); // 公开物品 + 用户自己的私有物品
    }

    public function test_store_creates_new_item()
    {
        $itemData = [
            'name' => 'Test Item',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Test Item');
        $response->assertJsonPath('data.description', 'Test Description');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('thing_items', [
            'name' => 'Test Item',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_with_images()
    {
        $images = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.jpg'),
        ];

        $itemData = [
            'name' => 'Test Item with Images',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'images' => $images,
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(201);

        $item = Item::where('name', 'Test Item with Images')->first();
        $this->assertNotNull($item);
        $this->assertCount(2, $item->images);
    }

    public function test_store_with_tags()
    {
        $tags = Tag::factory()->count(3)->create(['user_id' => $this->user->id]);
        $tagIds = $tags->pluck('id')->toArray();

        $itemData = [
            'name' => 'Test Item with Tags',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'tag_ids' => $tagIds,
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(201);

        $item = Item::where('name', 'Test Item with Tags')->first();
        $this->assertNotNull($item);
        $this->assertCount(3, $item->tags);
    }

    public function test_store_with_tags_field_accepts_owned_tag_ids(): void
    {
        $tags = Tag::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->postJson('/api/things/items', [
            'name' => 'Item With Tags Field',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'tags' => $tags->pluck('id')->all(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Item With Tags Field');

        $item = Item::where('name', 'Item With Tags Field')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            $tags->pluck('id')->all(),
            $item->tags()->pluck('thing_tags.id')->all()
        );
    }

    public function test_store_rejects_tag_ids_owned_by_another_user(): void
    {
        $otherUser = User::factory()->create();
        $foreignTag = Tag::factory()->create(['user_id' => $otherUser->id]);

        $itemData = [
            'name' => 'Item With Foreign Tag',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'tag_ids' => [$foreignTag->id],
        ];

        $response = $this->postJson('/api/things/items', $itemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_ids.0']);

        $this->assertDatabaseMissing('thing_items', [
            'name' => 'Item With Foreign Tag',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_category_id_owned_by_another_user(): void
    {
        $otherUser = User::factory()->create();
        $foreignCategory = ItemCategory::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postJson('/api/things/items', [
            'name' => 'Item With Foreign Category',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $foreignCategory->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);

        $this->assertDatabaseMissing('thing_items', [
            'name' => 'Item With Foreign Category',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_spot_id_owned_by_another_user(): void
    {
        $otherUser = User::factory()->create();
        $foreignSpot = Spot::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postJson('/api/things/items', [
            'name' => 'Item With Foreign Spot',
            'description' => 'Test Description',
            'status' => 'active',
            'spot_id' => $foreignSpot->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spot_id']);

        $this->assertDatabaseMissing('thing_items', [
            'name' => 'Item With Foreign Spot',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_image_paths_outside_authenticated_user_upload_directory(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->postJson('/api/things/items', [
            'name' => 'Item With Foreign Upload',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'image_paths' => ["uploads/{$otherUser->id}/foreign.jpg"],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_paths.0']);

        $this->assertDatabaseMissing('thing_items', [
            'name' => 'Item With Foreign Upload',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_image_paths_with_directory_traversal_segments(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->postJson('/api/things/items', [
            'name' => 'Item With Traversal Upload',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'image_paths' => ["uploads/{$this->user->id}/../{$otherUser->id}/foreign.jpg"],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_paths.0']);

        $this->assertDatabaseMissing('thing_items', [
            'name' => 'Item With Traversal Upload',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_origin_image_paths_from_upload_response(): void
    {
        $response = $this->postJson('/api/things/items', [
            'name' => 'Item With Origin Upload Path',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'image_paths' => ["uploads/{$this->user->id}/photo-origin.jpg"],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_paths.0']);

        $this->assertDatabaseMissing('thing_items', [
            'name' => 'Item With Origin Upload Path',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_update_rejects_primary_image_id_from_another_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $ownedPrimaryImage = ItemImage::factory()->primary()->create(['item_id' => $item->id]);

        $foreignItem = Item::factory()->create(['user_id' => $this->user->id]);
        $foreignImage = ItemImage::factory()->create(['item_id' => $foreignItem->id, 'is_primary' => false]);

        $response = $this->putJson("/api/things/items/{$item->id}", [
            'name' => 'Updated Name',
            'primary_image_id' => $foreignImage->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_image_id']);

        $this->assertDatabaseHas('thing_item_images', [
            'id' => $ownedPrimaryImage->id,
            'item_id' => $item->id,
            'is_primary' => true,
        ]);
    }

    public function test_update_rejects_image_ids_from_another_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $ownedImage = ItemImage::factory()->create(['item_id' => $item->id]);

        $foreignItem = Item::factory()->create(['user_id' => $this->user->id]);
        $foreignImage = ItemImage::factory()->create(['item_id' => $foreignItem->id]);

        $response = $this->putJson("/api/things/items/{$item->id}", [
            'name' => 'Updated Name',
            'image_ids' => [$ownedImage->id, $foreignImage->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_ids.1']);

        $this->assertDatabaseHas('thing_item_images', [
            'id' => $ownedImage->id,
            'item_id' => $item->id,
        ]);
        $this->assertDatabaseHas('thing_item_images', [
            'id' => $foreignImage->id,
            'item_id' => $foreignItem->id,
        ]);
    }

    public function test_update_rejects_image_order_ids_from_another_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $ownedImage = ItemImage::factory()->create(['item_id' => $item->id, 'sort_order' => 1]);

        $foreignItem = Item::factory()->create(['user_id' => $this->user->id]);
        $foreignImage = ItemImage::factory()->create(['item_id' => $foreignItem->id, 'sort_order' => 1]);

        $response = $this->putJson("/api/things/items/{$item->id}", [
            'name' => 'Updated Name',
            'image_order' => [$ownedImage->id, $foreignImage->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image_order.1']);

        $this->assertDatabaseHas('thing_item_images', [
            'id' => $ownedImage->id,
            'item_id' => $item->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('thing_item_images', [
            'id' => $foreignImage->id,
            'item_id' => $foreignItem->id,
            'sort_order' => 1,
        ]);
    }

    public function test_update_rejects_delete_images_from_another_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $ownedImage = ItemImage::factory()->create(['item_id' => $item->id]);

        $foreignItem = Item::factory()->create(['user_id' => $this->user->id]);
        $foreignImage = ItemImage::factory()->create(['item_id' => $foreignItem->id]);

        $response = $this->putJson("/api/things/items/{$item->id}", [
            'name' => 'Updated Name',
            'delete_images' => [$ownedImage->id, $foreignImage->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['delete_images.1']);

        $this->assertDatabaseHas('thing_item_images', [
            'id' => $ownedImage->id,
            'item_id' => $item->id,
        ]);
        $this->assertDatabaseHas('thing_item_images', [
            'id' => $foreignImage->id,
            'item_id' => $foreignItem->id,
        ]);
    }

    public function test_show_returns_item_details()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson("/api/things/items/{$item->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
            ],
        ]);
    }

    public function test_show_returns_403_for_inaccessible_item()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        $response = $this->getJson("/api/things/items/{$item->id}");

        $response->assertStatus(403);
    }

    public function test_update_modifies_item()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => 'Updated Item Name',
            'description' => 'Updated Description',
            'status' => 'inactive',
        ];

        $response = $this->putJson("/api/things/items/{$item->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Item Name');
        $response->assertJsonPath('data.description', 'Updated Description');
        $response->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('thing_items', [
            'id' => $item->id,
            'name' => 'Updated Item Name',
        ]);
    }

    public function test_update_returns_403_for_unauthorized_user()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $updateData = [
            'name' => 'Updated Item Name',
        ];

        $response = $this->putJson("/api/things/items/{$item->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_destroy_deletes_item()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/things/items/{$item->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('thing_items', ['id' => $item->id]);
    }

    public function test_destroy_deletes_item_image_files_from_storage(): void
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'items/' . $item->id . '/image.jpg',
        ]);

        Storage::disk('public')->put($image->path, 'image-content');
        Storage::disk('public')->put('items/' . $item->id . '/image-thumb.jpg', 'thumb-content');
        Storage::disk('public')->put('items/' . $item->id . '/image-origin.jpg', 'origin-content');

        $this->assertTrue(Storage::disk('public')->exists($image->path));
        $this->assertTrue(Storage::disk('public')->exists('items/' . $item->id . '/image-thumb.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('items/' . $item->id . '/image-origin.jpg'));

        $response = $this->deleteJson("/api/things/items/{$item->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('thing_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('thing_item_images', ['id' => $image->id]);
        $this->assertFalse(Storage::disk('public')->exists($image->path));
        $this->assertFalse(Storage::disk('public')->exists('items/' . $item->id . '/image-thumb.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('items/' . $item->id . '/image-origin.jpg'));
    }

    public function test_destroy_returns_403_for_unauthorized_user()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/things/items/{$item->id}");

        $response->assertStatus(403);
    }

    public function test_search_returns_filtered_results()
    {
        Item::factory()->create([
            'name' => 'Apple iPhone',
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
        Item::factory()->create([
            'name' => 'Samsung Galaxy',
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/things/search?q=iPhone');

        $response->assertStatus(200);
        $data = $response->json('results');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('iPhone', $data[0]['name']);
    }

    public function test_categories_returns_all_categories()
    {
        ItemCategory::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/things/items/categories');

        $response->assertStatus(200);
        $categories = $response->json('data');
        $this->assertGreaterThanOrEqual(5, count($categories));
    }

    public function test_index_with_name_filter()
    {
        Item::factory()->create([
            'name' => 'Special Item',
            'user_id' => $this->user->id,
        ]);
        Item::factory()->create([
            'name' => 'Regular Item',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/things/items?filter[name]=Special');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Special Item', $data[0]['name']);
    }

    public function test_index_with_status_filter()
    {
        Item::factory()->create([
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);
        Item::factory()->create([
            'status' => 'inactive',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/things/items?filter[status]=active');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('active', $data[0]['status']);
    }

    public function test_index_with_tags_filter()
    {
        $tag = Tag::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $item->tags()->attach($tag);

        Item::factory()->create([
            'user_id' => $this->user->id,
        ]); // 没有标签的物品

        $response = $this->getJson("/api/things/items?filter[tags]={$tag->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($item->id, $data[0]['id']);
    }

    public function test_search_suggestions_returns_matching_items()
    {
        Item::factory()->create([
            'name' => 'iPhone 15 Pro',
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        Item::factory()->create([
            'name' => 'iPhone 14',
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        Item::factory()->create([
            'name' => 'Samsung Galaxy',
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/things/search/suggestions?q=iPhone');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }

    public function test_search_history_returns_user_searches()
    {
        $this->getJson('/api/things/search?q=test');
        $this->getJson('/api/things/search?q=phone');

        $response = $this->getJson('/api/things/search/history');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    public function test_clear_search_history()
    {
        $this->getJson('/api/things/search?q=test');

        $response = $this->deleteJson('/api/things/search/history');

        $response->assertStatus(200);
        $response->assertJson(['message' => '搜索历史已清除']);
    }

    public function test_relations_returns_item_relations()
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $item->relatedItems()->attach($relatedItem->id, ['relation_type' => 'related']);

        $response = $this->getJson("/api/things/items/{$item->id}/relations");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'related_items',
                'relating_items',
            ],
        ]);
        $this->assertCount(1, $response->json('data.related_items'));
    }

    public function test_add_relation_creates_relationship()
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'related',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('thing_item_relations', [
            'item_id' => $item->id,
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'related',
        ]);
    }

    public function test_remove_relation_deletes_relationship()
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $item->relatedItems()->attach($relatedItem->id, ['relation_type' => 'related']);

        $response = $this->deleteJson("/api/things/items/{$item->id}/relations/{$relatedItem->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('thing_item_relations', [
            'item_id' => $item->id,
            'related_item_id' => $relatedItem->id,
        ]);
    }

    public function test_batch_add_relations_creates_multiple_relationships()
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem1 = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem2 = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations/batch", [
            'relations' => [
                ['related_item_id' => $relatedItem1->id, 'relation_type' => 'related'],
                ['related_item_id' => $relatedItem2->id, 'relation_type' => 'accessory'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('thing_item_relations', [
            'item_id' => $item->id,
            'related_item_id' => $relatedItem1->id,
            'relation_type' => 'related',
        ]);
        $this->assertDatabaseHas('thing_item_relations', [
            'item_id' => $item->id,
            'related_item_id' => $relatedItem2->id,
            'relation_type' => 'accessory',
        ]);
    }

    public function test_add_relation_unauthorized_for_non_owner()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $otherUser->id, 'is_public' => true]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'related',
        ]);

        $response->assertStatus(403);
    }

    public function test_remove_relation_unauthorized_for_non_owner()
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $otherUser->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/things/items/{$item->id}/relations/{$relatedItem->id}");

        $response->assertStatus(403);
    }

    public function test_search_with_empty_q_returns_empty_results(): void
    {
        $response = $this->getJson('/api/things/search?q=');

        $response->assertStatus(200);
        $response->assertJson([
            'search_term' => '',
            'count' => 0,
            'results' => [],
        ]);
    }

    public function test_clear_search_history_returns_401_when_unauthenticated(): void
    {
        Auth::forgetGuards();

        $response = $this->deleteJson('/api/things/search/history');

        $response->assertStatus(401);
        $this->assertStringContainsString('Unauthenticated', (string) $response->json('message'));
    }

    public function test_search_history_returns_empty_when_unauthenticated(): void
    {
        Auth::forgetGuards();

        $response = $this->getJson('/api/things/search/history');

        $response->assertStatus(401);
    }

    public function test_relations_returns_related_items_and_relating_items_keys(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);
        $item->relatedItems()->attach($relatedItem->id, ['relation_type' => 'related']);

        $response = $this->getJson("/api/things/items/{$item->id}/relations");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'related_items',
                'relating_items',
            ],
        ]);
    }

    public function test_add_relation_returns_400_when_self_relation(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $item->id,
            'relation_type' => 'related',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => '不能关联自己']);
    }

    public function test_add_relation_validation_fails_without_related_item_id(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'relation_type' => 'related',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['related_item_id']);
    }

    public function test_add_relation_validation_fails_with_invalid_relation_type(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['relation_type']);
    }

    public function test_add_relation_returns_403_when_related_item_not_accessible(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        $privateItem = Item::factory()->create(['user_id' => $otherUser->id, 'is_public' => false]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $privateItem->id,
            'relation_type' => 'related',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => '无权访问关联的物品']);
    }

    public function test_add_relation_returns_400_when_duplicate(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);
        $item->relatedItems()->attach($relatedItem->id, ['relation_type' => 'related']);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'related',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => '该关联已存在']);
    }

    public function test_batch_add_relations_validation_fails_with_empty_relations(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations/batch", [
            'relations' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['relations']);
    }

    public function test_batch_add_relations_partial_success_returns_errors(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem1 = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem2 = Item::factory()->create(['user_id' => $this->user->id]);
        $item->relatedItems()->attach($relatedItem2->id, ['relation_type' => 'related']);

        $response = $this->postJson("/api/things/items/{$item->id}/relations/batch", [
            'relations' => [
                ['related_item_id' => $relatedItem1->id, 'relation_type' => 'related'],
                ['related_item_id' => $relatedItem2->id, 'relation_type' => 'related'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success_count', 1);
        $this->assertNotEmpty($response->json('data.errors'));
    }

    public function test_index_with_status_filter_all_returns_all_statuses(): void
    {
        Item::factory()->create(['status' => 'active', 'user_id' => $this->user->id]);
        Item::factory()->create(['status' => 'inactive', 'user_id' => $this->user->id]);

        $response = $this->getJson('/api/things/items?filter[status]=all');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_index_with_filter_own_returns_only_own_items(): void
    {
        Item::factory()->create(['is_public' => true, 'user_id' => User::factory()->create()->id]);
        Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/things/items?filter[own]=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->user->id, $data[0]['user_id']);
    }

    public function test_categories_returns_only_current_user_categories(): void
    {
        ItemCategory::factory()->create(['user_id' => $this->user->id]);
        ItemCategory::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->getJson('/api/things/items/categories');

        $response->assertStatus(200);
        $categories = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($categories));
        foreach ($categories as $cat) {
            $this->assertEquals($this->user->id, $cat['user_id']);
        }
    }

    public function test_index_with_description_filter(): void
    {
        Item::factory()->create([
            'description' => 'Unique desc keyword',
            'user_id' => $this->user->id,
        ]);
        Item::factory()->create([
            'description' => 'Other text',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/things/items?filter[description]=keyword');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('keyword', $data[0]['description']);
    }

    public function test_index_with_category_filter_uncategorized(): void
    {
        Item::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => null,
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson('/api/things/items?filter[category_id]=uncategorized');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNull($data[0]['category_id']);
    }

    public function test_index_with_category_filter_parent_includes_children(): void
    {
        $parent = ItemCategory::factory()->create(['user_id' => $this->user->id, 'parent_id' => null]);
        $child = ItemCategory::factory()->create(['user_id' => $this->user->id, 'parent_id' => $parent->id]);
        Item::factory()->create(['user_id' => $this->user->id, 'category_id' => $parent->id]);
        Item::factory()->create(['user_id' => $this->user->id, 'category_id' => $child->id]);
        Item::factory()->create(['user_id' => $this->user->id, 'category_id' => null]);

        $response = $this->getJson("/api/things/items?filter[category_id]={$parent->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_index_with_purchase_date_filter(): void
    {
        Item::factory()->create([
            'user_id' => $this->user->id,
            'purchase_date' => '2025-06-01',
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'purchase_date' => '2025-01-01',
        ]);

        $response = $this->getJson('/api/things/items?filter[purchase_date_from]=2025-05-01');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_index_with_price_filter(): void
    {
        Item::factory()->create([
            'user_id' => $this->user->id,
            'purchase_price' => 100,
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'purchase_price' => 50,
        ]);

        $response = $this->getJson('/api/things/items?filter[price_from]=80');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertGreaterThanOrEqual(80, (float) $data[0]['purchase_price']);
    }

    public function test_store_validation_fails_without_name(): void
    {
        $response = $this->postJson('/api/things/items', [
            'description' => 'No name',
            'status' => 'active',
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_invalid_status(): void
    {
        $response = $this->postJson('/api/things/items', [
            'name' => 'Test',
            'status' => 'invalid_status',
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_add_relation_with_optional_description(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $relatedItem = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'related',
            'description' => 'Optional relation note',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('thing_item_relations', [
            'item_id' => $item->id,
            'related_item_id' => $relatedItem->id,
            'relation_type' => 'related',
            'description' => 'Optional relation note',
        ]);
    }

    public function test_relations_returns_403_for_inaccessible_item(): void
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $otherUser->id, 'is_public' => false]);

        $response = $this->getJson("/api/things/items/{$item->id}/relations");

        $response->assertStatus(403);
        $response->assertJson(['message' => '无权查看此物品']);
    }

    public function test_search_respects_limit_param(): void
    {
        Item::factory()->count(5)->create([
            'name' => 'Phone Item',
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/things/search?q=Phone&limit=2');

        $response->assertStatus(200);
        $results = $response->json('results');
        $this->assertLessThanOrEqual(2, count($results));
    }

    public function test_show_returns_403_message_for_inaccessible_item(): void
    {
        $otherUser = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $otherUser->id, 'is_public' => false]);

        $response = $this->getJson("/api/things/items/{$item->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => '无权查看此物品']);
    }

    public function test_batch_add_relations_returns_403_for_unauthorized_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $item = Item::factory()->create(['user_id' => $otherUser->id, 'is_public' => false]);
        $relatedItem = Item::factory()->create(['user_id' => $user->id, 'is_public' => true]);

        $response = $this->postJson("/api/things/items/{$item->id}/relations/batch", [
            'relations' => [
                [
                    'related_item_id' => $relatedItem->id,
                    'relation_type' => 'related',
                ],
            ],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => '无权修改此物品']);
    }
}
