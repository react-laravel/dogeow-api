<?php

namespace Tests\Feature\Controllers\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // ==================== Index Tests ====================

    public function test_index_returns_user_tags()
    {
        $userTag = Tag::factory()->create(['user_id' => $this->user->id]);
        $otherUserTag = Tag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $userTag->id])
            ->assertJsonMissing(['id' => $otherUserTag->id]);
    }

    public function test_index_includes_items_count()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->tags()->attach($tag->id);

        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'color',
                    'user_id',
                    'created_at',
                    'updated_at',
                    'items_count',
                ],
            ])
            ->assertJsonFragment(['items_count' => 1]);
    }

    public function test_index_orders_by_created_at_desc()
    {
        $tag1 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);
        $tag2 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($tag2->id, $data[0]['id']);
        $this->assertEquals($tag1->id, $data[1]['id']);
    }

    public function test_index_returns_empty_array_when_no_tags()
    {
        $response = $this->getJson('/api/things/tags');

        $response->assertStatus(200)
            ->assertJson([]);
    }

    // ==================== Store Tests ====================

    public function test_store_creates_new_tag()
    {
        $data = ['name' => 'Test Tag'];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'user_id' => $this->user->id,
                'color' => '#3b82f6',
            ])
            ->assertJsonStructure([
                'id',
                'name',
                'color',
                'user_id',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('thing_tags', [
            'name' => 'Test Tag',
            'user_id' => $this->user->id,
            'color' => '#3b82f6',
        ]);
    }

    public function test_store_creates_tag_with_custom_color()
    {
        $data = [
            'name' => 'Test Tag',
            'color' => '#ff0000',
        ];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Test Tag',
                'color' => '#ff0000',
            ]);

        $this->assertDatabaseHas('thing_tags', [
            'name' => 'Test Tag',
            'color' => '#ff0000',
        ]);
    }

    public function test_store_validation_fails_without_name()
    {
        $response = $this->postJson('/api/things/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_long_name()
    {
        $data = ['name' => str_repeat('a', 51)];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_fails_with_invalid_color()
    {
        $data = [
            'name' => 'Test Tag',
            'color' => 'invalid-color',
        ];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_store_validation_fails_with_duplicate_name_for_same_user()
    {
        Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Existing Tag',
        ]);

        $data = ['name' => 'Existing Tag'];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_allows_same_name_for_different_users()
    {
        Tag::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Shared Tag',
        ]);

        $data = ['name' => 'Shared Tag'];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(201)
            ->assertJson(['name' => 'Shared Tag']);

        $this->assertDatabaseHas('thing_tags', [
            'name' => 'Shared Tag',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_validates_color_hex_format()
    {
        $data = [
            'name' => 'Test Tag',
            'color' => '#GGGGGG',
        ];

        $response = $this->postJson('/api/things/tags', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_store_accepts_valid_hex_colors()
    {
        $validColors = ['#ff0000', '#00ff00', '#0000ff', '#ffffff', '#000000'];

        foreach ($validColors as $color) {
            $data = [
                'name' => "Tag with color {$color}",
                'color' => $color,
            ];

            $response = $this->postJson('/api/things/tags', $data);
            $response->assertStatus(201);
        }
    }

    // ==================== Show Tests ====================

    public function test_show_returns_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'user_id' => $this->user->id,
            ])
            ->assertJsonStructure([
                'id',
                'name',
                'color',
                'user_id',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_show_returns_404_for_other_user_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->getJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_tag()
    {
        $response = $this->getJson('/api/things/tags/999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_deleted_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $tag->delete();

        $response = $this->getJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    // ==================== Update Tests ====================

    public function test_update_modifies_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $data = [
            'name' => 'Updated Tag',
            'color' => '#00ff00',
        ];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $tag->id,
                'name' => 'Updated Tag',
                'color' => '#00ff00',
            ]);

        $this->assertDatabaseHas('thing_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'color' => '#00ff00',
        ]);
    }

    public function test_update_partial_fields()
    {
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
            'color' => '#ff0000',
        ]);

        // Update only name
        $response = $this->putJson("/api/things/tags/{$tag->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Name',
                'color' => '#ff0000',
            ]);

        // Update only color (must include name as it's required)
        $response = $this->putJson("/api/things/tags/{$tag->id}", [
            'name' => 'Updated Name',
            'color' => '#00ff00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Name',
                'color' => '#00ff00',
            ]);
    }

    public function test_update_returns_404_for_other_user_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->otherUser->id]);
        $data = ['name' => 'Updated Tag'];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(404);
    }

    public function test_update_validation_fails_with_long_name()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $data = ['name' => str_repeat('a', 51)];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validation_fails_with_invalid_color()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $data = [
            'name' => 'Test Tag',
            'color' => 'invalid-color',
        ];

        $response = $this->putJson("/api/things/tags/{$tag->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_update_validates_name_uniqueness_per_user()
    {
        $tag1 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Tag 1',
        ]);
        $tag2 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Tag 2',
        ]);

        $response = $this->putJson("/api/things/tags/{$tag2->id}", [
            'name' => 'Tag 1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_allows_same_name_for_different_users()
    {
        $otherUserTag = Tag::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Shared Tag',
        ]);
        $userTag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Tag',
        ]);

        $response = $this->putJson("/api/things/tags/{$userTag->id}", [
            'name' => 'Shared Tag',
        ]);

        $response->assertStatus(200)
            ->assertJson(['name' => 'Shared Tag']);
    }

    public function test_update_returns_404_for_nonexistent_tag()
    {
        $response = $this->putJson('/api/things/tags/999', [
            'name' => 'Updated Tag',
        ]);

        $response->assertStatus(404);
    }

    public function test_update_returns_404_for_deleted_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $tag->delete();

        $response = $this->putJson("/api/things/tags/{$tag->id}", [
            'name' => 'Updated Tag',
        ]);

        $response->assertStatus(404);
    }

    // ==================== Destroy Tests ====================

    public function test_destroy_deletes_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(204);

        // 由于使用了 SoftDeletes，数据仍然存在但被标记为已删除
        $this->assertSoftDeleted('thing_tags', ['id' => $tag->id]);
    }

    public function test_destroy_returns_404_for_other_user_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    public function test_destroy_detaches_items_before_deletion()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->tags()->attach($tag->id);

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(204);

        // 由于使用了 SoftDeletes，数据仍然存在但被标记为已删除
        $this->assertSoftDeleted('thing_tags', ['id' => $tag->id]);

        // 检查关联关系已被删除
        $this->assertDatabaseMissing('thing_item_tag', [
            'thing_tag_id' => $tag->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_tag()
    {
        $response = $this->deleteJson('/api/things/tags/999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_404_for_deleted_tag()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $tag->delete();

        $response = $this->deleteJson("/api/things/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    // ==================== Edge Cases ====================

    public function test_store_with_empty_string_name_fails()
    {
        $response = $this->postJson('/api/things/tags', ['name' => '']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_with_whitespace_only_name_fails()
    {
        $response = $this->postJson('/api/things/tags', ['name' => '   ']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_with_empty_string_name_fails()
    {
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/things/tags/{$tag->id}", ['name' => '']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_with_null_color_uses_default()
    {
        $response = $this->postJson('/api/things/tags', [
            'name' => 'Test Tag',
            // Don't send color at all, let it use default
        ]);

        $response->assertStatus(201)
            ->assertJson(['color' => '#3b82f6']);
    }

    public function test_update_with_null_color_keeps_existing()
    {
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'color' => '#ff0000',
        ]);

        $response = $this->putJson("/api/things/tags/{$tag->id}", [
            'name' => 'Updated Tag',
            // Don't send color at all, let it keep existing
        ]);

        $response->assertStatus(200)
            ->assertJson(['color' => '#ff0000']);
    }
}
