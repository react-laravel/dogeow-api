<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\ItemImage;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ItemCategory $category;

    protected Spot $spot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = ItemCategory::factory()->create();
        $this->spot = Spot::factory()->create();
    }

    public function test_item_creation()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'description' => 'Test Description',
            'user_id' => $this->user->id,
            'quantity' => 5,
            'status' => 'active',
            'category_id' => $this->category->id,
            'spot_id' => $this->spot->id,
            'is_public' => true,
        ]);

        $this->assertDatabaseHas('thing_items', [
            'name' => 'Test Item',
            'description' => 'Test Description',
            'user_id' => $this->user->id,
            'quantity' => 5,
            'status' => 'active',
            'is_public' => true,
        ]);
    }

    public function test_item_relationships()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'spot_id' => $this->spot->id,
        ]);

        $this->assertInstanceOf(User::class, $item->user);
        $this->assertEquals($this->user->id, $item->user->id);

        $this->assertInstanceOf(ItemCategory::class, $item->category);
        $this->assertEquals($this->category->id, $item->category->id);

        $this->assertInstanceOf(Spot::class, $item->spot);
        $this->assertEquals($this->spot->id, $item->spot->id);
    }

    public function test_item_images_relationship()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'user_id' => $this->user->id,
        ]);

        $image1 = ItemImage::create([
            'item_id' => $item->id,
            'path' => 'items/1/image1.jpg',
            'is_primary' => true,
        ]);

        $image2 = ItemImage::create([
            'item_id' => $item->id,
            'path' => 'items/1/image2.jpg',
            'is_primary' => false,
        ]);

        $this->assertCount(2, $item->images);
        $this->assertTrue($item->images->contains($image1));
        $this->assertTrue($item->images->contains($image2));
    }

    public function test_primary_image_relationship()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'user_id' => $this->user->id,
        ]);

        $primaryImage = ItemImage::create([
            'item_id' => $item->id,
            'path' => 'items/1/primary.jpg',
            'is_primary' => true,
        ]);

        $this->assertInstanceOf(ItemImage::class, $item->primaryImage);
        $this->assertEquals($primaryImage->id, $item->primaryImage->id);
    }

    public function test_thumbnail_url_attribute()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'user_id' => $this->user->id,
        ]);

        // Test with primary image
        $primaryImage = ItemImage::create([
            'item_id' => $item->id,
            'path' => 'items/1/primary.jpg',
            'is_primary' => true,
        ]);

        $this->assertNotNull($item->thumbnail_url);
        $this->assertStringContainsString('primary-thumb.jpg', $item->thumbnail_url);

        // Test without primary image but with regular image
        $item2 = Item::create([
            'name' => 'Test Item 2',
            'user_id' => $this->user->id,
        ]);

        $regularImage = ItemImage::create([
            'item_id' => $item2->id,
            'path' => 'items/2/regular.jpg',
            'is_primary' => false,
        ]);

        $this->assertNotNull($item2->thumbnail_url);
        $this->assertStringContainsString('regular-thumb.jpg', $item2->thumbnail_url);

        // Test without any images
        $item3 = Item::create([
            'name' => 'Test Item 3',
            'user_id' => $this->user->id,
        ]);

        $this->assertNull($item3->thumbnail_url);
    }

    public function test_thumbnail_url_does_not_trigger_extra_queries_when_relations_are_preloaded(): void
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'items/1/preloaded.jpg',
            'is_primary' => true,
        ]);

        $loadedItem = Item::with(['primaryImage', 'images'])->findOrFail($item->id);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $thumbnailUrl = $loadedItem->thumbnail_url;

        $this->assertNotNull($thumbnailUrl);
        $this->assertStringContainsString('preloaded-thumb.jpg', $thumbnailUrl);
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_search_scope()
    {
        $item1 = Item::create([
            'name' => 'Apple iPhone',
            'description' => 'Smartphone',
            'user_id' => $this->user->id,
        ]);

        $item2 = Item::create([
            'name' => 'Samsung Galaxy',
            'description' => 'Android phone',
            'user_id' => $this->user->id,
        ]);

        $item3 = Item::create([
            'name' => 'MacBook Pro',
            'description' => 'Laptop computer',
            'user_id' => $this->user->id,
        ]);

        // Search by name
        $results = Item::search('iPhone')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($item1->id, $results->first()->id);

        // Search by description
        $results = Item::search('Android')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($item2->id, $results->first()->id);

        // Search by partial match
        $results = Item::search('phone')->get();
        $this->assertCount(2, $results);
    }

    public function test_to_searchable_array()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'description' => 'Test Description',
            'status' => 'active',
            'category_id' => $this->category->id,
            'is_public' => true,
            'user_id' => $this->user->id,
        ]);

        $searchableArray = $item->toSearchableArray();

        $this->assertArrayHasKey('id', $searchableArray);
        $this->assertArrayHasKey('name', $searchableArray);
        $this->assertArrayHasKey('description', $searchableArray);
        $this->assertArrayHasKey('status', $searchableArray);
        $this->assertArrayHasKey('category_id', $searchableArray);
        $this->assertArrayHasKey('is_public', $searchableArray);
        $this->assertArrayHasKey('user_id', $searchableArray);

        $this->assertEquals($item->id, $searchableArray['id']);
        $this->assertEquals('Test Item', $searchableArray['name']);
        $this->assertEquals('Test Description', $searchableArray['description']);
        $this->assertEquals('active', $searchableArray['status']);
        $this->assertEquals($this->category->id, $searchableArray['category_id']);
        $this->assertTrue($searchableArray['is_public']);
        $this->assertEquals($this->user->id, $searchableArray['user_id']);
    }

    public function test_item_with_dates()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'user_id' => $this->user->id,
            'purchase_date' => '2023-01-15',
            'expiry_date' => '2024-01-15',
            'purchase_price' => 99.99,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $item->purchase_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $item->expiry_date);
        $this->assertEquals('99.99', $item->purchase_price);
    }

    public function test_item_tags_relationship()
    {
        $item = Item::create([
            'name' => 'Test Item',
            'user_id' => $this->user->id,
        ]);

        // This would require Tag model and pivot table
        // For now, just test that the relationship method exists
        $this->assertTrue(method_exists($item, 'tags'));
    }

    public function test_item_factory()
    {
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(Item::class, $item);
        $this->assertNotEmpty($item->name);
        $this->assertEquals($this->user->id, $item->user_id);
    }

    public function test_related_items_relationship()
    {
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);

        $item1->relatedItems()->attach($item2->id, [
            'relation_type' => 'related',
            'description' => 'similar item',
        ]);

        $this->assertCount(1, $item1->relatedItems);
        $this->assertEquals($item2->id, $item1->relatedItems->first()->id);
    }

    public function test_relating_items_relationship()
    {
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);

        // Item1 relates to Item2 (stored in thing_item_relations as item_id=1, related_item_id=2)
        $item1->relatedItems()->attach($item2->id, ['relation_type' => 'related']);

        // Item2 should have relatingItems pointing back to Item1
        $this->assertCount(1, $item2->relatingItems);
    }

    public function test_all_relations_merges_both_directions()
    {
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);
        $item3 = Item::factory()->create(['user_id' => $this->user->id]);

        // item1 -> item2 (related)
        $item1->relatedItems()->attach($item2->id, ['relation_type' => 'related']);
        // item3 -> item1 (relating)
        $item3->relatedItems()->attach($item1->id, ['relation_type' => 'related']);

        $allRelations = $item1->allRelations();

        $this->assertCount(2, $allRelations);
    }

    public function test_get_relations_by_type()
    {
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);
        $item3 = Item::factory()->create(['user_id' => $this->user->id]);

        $item1->relatedItems()->attach($item2->id, ['relation_type' => 'accessory']);
        $item1->relatedItems()->attach($item3->id, ['relation_type' => 'replacement']);

        $accessoryRelations = $item1->getRelationsByType('accessory');

        $this->assertCount(1, $accessoryRelations);
        $this->assertEquals($item2->id, $accessoryRelations->first()->id);
    }

    public function test_add_relation()
    {
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);

        $item1->addRelation($item2->id, 'related', 'test description');

        $this->assertCount(1, $item1->relatedItems);
        $this->assertEquals('related', $item1->relatedItems->first()->pivot->relation_type);
        $this->assertEquals('test description', $item1->relatedItems->first()->pivot->description);
    }

    public function test_remove_relation()
    {
        $item1 = Item::factory()->create(['user_id' => $this->user->id]);
        $item2 = Item::factory()->create(['user_id' => $this->user->id]);

        $item1->relatedItems()->attach($item2->id, ['relation_type' => 'related']);

        $this->assertCount(1, $item1->relatedItems);

        $item1->removeRelation($item2->id);

        $this->assertCount(0, $item1->fresh()->relatedItems);
    }
}
