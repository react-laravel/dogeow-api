<?php

namespace Tests\Feature\Controllers\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_search_items(): void
    {
        $this->getJson('/api/things/search?q=Widget')->assertStatus(401);
    }

    public function test_empty_query_returns_empty_results_without_hitting_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Keyboard',
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/things/search?q=');

        $response->assertOk()
            ->assertJsonPath('search_term', '')
            ->assertJsonPath('count', 0)
            ->assertJsonPath('results', []);

        $this->assertDatabaseCount('thing_search_history', 0);
    }

    public function test_search_returns_public_matches_and_the_authenticated_users_private_matches(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $public = Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Public Widget',
            'is_public' => true,
        ]);
        $ownPrivate = Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Private Widget',
            'is_public' => false,
        ]);
        Item::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Private Widget',
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/things/search?q=Widget');

        $response->assertOk()
            ->assertJsonPath('search_term', 'Widget')
            ->assertJsonPath('count', 2);

        $ids = array_column($response->json('results'), 'id');
        sort($ids);
        $expectedIds = [$public->id, $ownPrivate->id];
        sort($expectedIds);
        $this->assertSame($expectedIds, $ids);
    }

    public function test_search_applies_category_and_tag_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $category = ItemCategory::factory()->create(['user_id' => $user->id]);
        $otherCategory = ItemCategory::factory()->create(['user_id' => $user->id]);
        $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'office']);

        $match = Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Desk Lamp',
            'category_id' => $category->id,
            'is_public' => true,
        ]);
        $match->tags()->attach($tag->id);

        Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Desk Chair',
            'category_id' => $otherCategory->id,
            'is_public' => true,
        ]);

        $response = $this->getJson(
            '/api/things/search?q=Desk&category_id=' . $category->id . '&tags[]=office'
        );

        $response->assertOk()->assertJsonPath('count', 1);
        $this->assertSame([$match->id], array_column($response->json('results'), 'id'));
    }

    public function test_authenticated_search_records_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Item::factory()->create([
            'user_id' => $user->id,
            'name' => 'Monitor',
            'is_public' => true,
        ]);

        $this->getJson('/api/things/search?q=Monitor')->assertOk();

        $this->assertDatabaseHas('thing_search_history', [
            'user_id' => $user->id,
            'search_term' => 'Monitor',
            'results_count' => 1,
        ]);
    }

    public function test_guest_search_history_requires_authentication(): void
    {
        $this->getJson('/api/things/search/history')->assertStatus(401);
    }

    public function test_user_search_history_is_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DB::table('thing_search_history')->insert([
            [
                'user_id' => $user->id,
                'search_term' => 'mine',
                'results_count' => 1,
                'filters' => '{}',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $other->id,
                'search_term' => 'theirs',
                'results_count' => 2,
                'filters' => '{}',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/things/search/history');

        $response->assertOk();
        $terms = collect($response->json('history'))->pluck('search_term')->all();
        $this->assertSame(['mine'], $terms);
    }

    public function test_clear_search_history_requires_auth_and_only_clears_own_rows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DB::table('thing_search_history')->insert([
            [
                'user_id' => $user->id,
                'search_term' => 'mine',
                'results_count' => 1,
                'filters' => '{}',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $other->id,
                'search_term' => 'theirs',
                'results_count' => 1,
                'filters' => '{}',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->deleteJson('/api/things/search/history')->assertStatus(401);

        Sanctum::actingAs($user);
        $this->deleteJson('/api/things/search/history')
            ->assertOk()
            ->assertJsonPath('message', '搜索历史已清除');

        $this->assertDatabaseMissing('thing_search_history', ['user_id' => $user->id]);
        $this->assertDatabaseHas('thing_search_history', [
            'user_id' => $other->id,
            'search_term' => 'theirs',
        ]);
    }
}
