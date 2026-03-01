<?php

namespace Tests\Feature;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThingItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = ItemCategory::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_public_items_and_users_own_private_items_only(): void
    {
        $publicItem = Item::factory()->public()->create([
            'user_id' => User::factory()->create()->id,
        ]);
        $ownPrivateItem = Item::factory()->private()->create([
            'user_id' => $this->user->id,
        ]);
        $otherPrivateItem = Item::factory()->private()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/things/items');

        $response->assertStatus(200);

        $itemIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($publicItem->id, $itemIds);
        $this->assertContains($ownPrivateItem->id, $itemIds);
        $this->assertNotContains($otherPrivateItem->id, $itemIds);
    }

    public function test_store_creates_item_with_default_quantity_and_tag_ids(): void
    {
        $tags = Tag::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/things/items', [
            'name' => 'Mechanical Keyboard',
            'description' => 'Office keyboard',
            'status' => 'active',
            'category_id' => $this->category->id,
            'is_public' => false,
            'tag_ids' => $tags->pluck('id')->all(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', '物品创建成功')
            ->assertJsonPath('item.name', 'Mechanical Keyboard')
            ->assertJsonPath('item.user_id', $this->user->id)
            ->assertJsonPath('item.quantity', 1);

        $item = Item::where('name', 'Mechanical Keyboard')->firstOrFail();
        $this->assertSame($tags->pluck('id')->sort()->values()->all(), $item->tags()->pluck('thing_tags.id')->sort()->values()->all());
    }

    public function test_show_returns_403_for_other_users_private_item(): void
    {
        $item = Item::factory()->private()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson("/api/things/items/{$item->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => '无权查看此物品',
            ]);
    }

    public function test_update_returns_403_for_other_users_item(): void
    {
        $item = Item::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->putJson("/api/things/items/{$item->id}", [
            'name' => 'Updated name',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => '无权更新此物品',
            ]);
    }

    public function test_destroy_deletes_owned_item(): void
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/things/items/{$item->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('thing_items', [
            'id' => $item->id,
        ]);
    }

    public function test_search_returns_empty_payload_when_query_is_missing(): void
    {
        $response = $this->getJson('/api/things/search');

        $response->assertStatus(200)
            ->assertJson([
                'search_term' => '',
                'count' => 0,
                'results' => [],
            ]);
    }

    public function test_search_records_history_and_exposes_suggestions_and_history(): void
    {
        Item::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Apple Watch',
            'description' => 'Wearable device',
            'is_public' => true,
        ]);

        $this->getJson('/api/things/search?q=apple')->assertStatus(200);
        $this->travel(1)->seconds();
        $this->getJson('/api/things/search?q=apple')->assertStatus(200);
        $this->travel(1)->seconds();
        $this->getJson('/api/things/search?q=app')->assertStatus(200);

        $this->assertDatabaseHas('thing_search_history', [
            'user_id' => $this->user->id,
            'search_term' => 'apple',
        ]);

        $suggestionsResponse = $this->getJson('/api/things/search/suggestions?q=app&limit=5');
        $suggestionsResponse->assertStatus(200);
        $this->assertSame(['apple', 'app'], $suggestionsResponse->json());

        $historyResponse = $this->getJson('/api/things/search/history?limit=10');
        $historyResponse->assertStatus(200);
        $history = collect($historyResponse->json());
        $this->assertCount(2, $history);
        $appleHistory = $history->firstWhere('search_term', 'apple');
        $this->assertNotNull($appleHistory);
        $this->assertSame(2, (int) $appleHistory['search_count']);
    }

    public function test_clear_search_history_removes_current_users_history(): void
    {
        $this->getJson('/api/things/search?q=apple')->assertStatus(200);

        $response = $this->deleteJson('/api/things/search/history');

        $response->assertStatus(200)
            ->assertJson([
                'message' => '搜索历史已清除',
            ]);

        $this->assertDatabaseMissing('thing_search_history', [
            'user_id' => $this->user->id,
            'search_term' => 'apple',
        ]);
    }

    public function test_relations_returns_related_and_referencing_items(): void
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
        $relatedItem = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
        $relatingItem = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $item->addRelation($relatedItem->id, 'related', 'Forward relation');
        $relatingItem->addRelation($item->id, 'accessory', 'Reverse relation');

        $response = $this->getJson("/api/things/items/{$item->id}/relations");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'related_items')
            ->assertJsonCount(1, 'relating_items')
            ->assertJsonPath('related_items.0.id', $relatedItem->id)
            ->assertJsonPath('relating_items.0.id', $relatingItem->id);
    }

    public function test_add_relation_validates_self_and_visibility_rules(): void
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $selfResponse = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $item->id,
            'relation_type' => 'related',
        ]);

        $selfResponse->assertStatus(400)
            ->assertJson([
                'message' => '不能关联自己',
            ]);

        $hiddenItem = Item::factory()->private()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $hiddenResponse = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $hiddenItem->id,
            'relation_type' => 'related',
        ]);

        $hiddenResponse->assertStatus(403)
            ->assertJson([
                'message' => '无权访问关联的物品',
            ]);
    }

    public function test_add_remove_and_batch_add_relations(): void
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
        $firstRelated = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);
        $secondRelated = Item::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
        ]);

        $addResponse = $this->postJson("/api/things/items/{$item->id}/relations", [
            'related_item_id' => $firstRelated->id,
            'relation_type' => 'bundle',
            'description' => 'Bundled together',
        ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('message', '关联添加成功')
            ->assertJsonCount(1, 'relations');

        $batchResponse = $this->postJson("/api/things/items/{$item->id}/relations/batch", [
            'relations' => [
                [
                    'related_item_id' => $firstRelated->id,
                    'relation_type' => 'bundle',
                    'description' => 'Duplicate relation',
                ],
                [
                    'related_item_id' => $secondRelated->id,
                    'relation_type' => 'accessory',
                    'description' => 'Valid relation',
                ],
            ],
        ]);

        $batchResponse->assertStatus(200)
            ->assertJsonPath('success_count', 1)
            ->assertJsonCount(1, 'errors')
            ->assertJsonCount(2, 'relations');

        $removeResponse = $this->deleteJson("/api/things/items/{$item->id}/relations/{$firstRelated->id}");

        $removeResponse->assertStatus(200)
            ->assertJsonPath('message', '关联删除成功')
            ->assertJsonCount(1, 'relations')
            ->assertJsonPath('relations.0.id', $secondRelated->id);
    }
}
