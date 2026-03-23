<?php

namespace Tests\Unit\Controllers\Thing;

use App\Http\Controllers\Api\Thing\ItemController;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\User;
use App\Services\Thing\ItemSearchService;
use App\Services\Thing\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class ItemControllerUnitTest extends TestCase
{
    use RefreshDatabase;

    private ItemController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ItemController(
            Mockery::mock(ItemService::class),
            Mockery::mock(ItemSearchService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_request_limit_returns_default_when_limit_missing(): void
    {
        $request = Request::create('/api/things/items', 'GET');

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('getRequestLimit');
        $method->setAccessible(true);

        $limit = $method->invoke($this->controller, $request);

        $this->assertSame(10, $limit);
    }

    public function test_get_request_limit_returns_custom_limit_from_request(): void
    {
        $request = Request::create('/api/things/items', 'GET', ['limit' => 37]);

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('getRequestLimit');
        $method->setAccessible(true);

        $limit = $method->invoke($this->controller, $request, 10);

        $this->assertSame(37, $limit);
    }

    public function test_apply_category_filter_with_uncategorized_only_returns_null_category_items(): void
    {
        Item::factory()->create(['category_id' => null]);
        $category = ItemCategory::factory()->create();
        Item::factory()->create(['category_id' => $category->id]);

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('applyCategoryFilter');
        $method->setAccessible(true);

        $query = Item::query();
        $method->invoke($this->controller, $query, 'uncategorized');

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertNull($results->first()->category_id);
    }

    public function test_apply_category_filter_with_nonexistent_category_id_uses_direct_filter(): void
    {
        Item::factory()->create(['category_id' => 99999]);
        Item::factory()->create(['category_id' => null]);

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('applyCategoryFilter');
        $method->setAccessible(true);

        $query = Item::query();
        $method->invoke($this->controller, $query, 99999);

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame(99999, $results->first()->category_id);
    }

    public function test_apply_category_filter_with_parent_category_includes_children(): void
    {
        $parent = ItemCategory::factory()->create(['parent_id' => null]);
        $child = ItemCategory::factory()->create(['parent_id' => $parent->id]);

        Item::factory()->create(['category_id' => $parent->id]);
        Item::factory()->create(['category_id' => $child->id]);
        Item::factory()->create(['category_id' => null]);

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('applyCategoryFilter');
        $method->setAccessible(true);

        $query = Item::query();
        $method->invoke($this->controller, $query, $parent->id);

        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertContains($parent->id, $results->pluck('category_id'));
        $this->assertContains($child->id, $results->pluck('category_id'));
    }

    public function test_apply_visibility_filter_for_guest_only_keeps_public_items(): void
    {
        Item::factory()->create(['is_public' => true]);
        Item::factory()->create(['is_public' => false]);

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('applyVisibilityFilter');
        $method->setAccessible(true);

        auth()->logout();
        $query = Item::query();
        $method->invoke($this->controller, $query);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertTrue((bool) $results->first()->is_public);
    }

    public function test_apply_visibility_filter_for_authenticated_user_keeps_public_and_own_private_items(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        Item::factory()->create(['is_public' => true, 'user_id' => $otherUser->id]);
        Item::factory()->create(['is_public' => false, 'user_id' => $owner->id]);
        Item::factory()->create(['is_public' => false, 'user_id' => $otherUser->id]);

        $reflection = new ReflectionClass(ItemController::class);
        $method = $reflection->getMethod('applyVisibilityFilter');
        $method->setAccessible(true);

        $this->actingAs($owner);
        $query = Item::query();
        $method->invoke($this->controller, $query);

        $results = $query->get();
        $this->assertCount(2, $results);
        $this->assertContains(1, $results->pluck('is_public')->map(fn ($value) => (int) $value)->all());
        $this->assertContains($owner->id, $results->pluck('user_id')->all());
    }
}
