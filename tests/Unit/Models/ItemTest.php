<?php

namespace Tests\Unit\Models;

use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\ItemImage;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\Thing\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected Item $item;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_item_has_correct_fillable_fields(): void
    {
        $fillable = [
            'name',
            'description',
            'user_id',
            'quantity',
            'status',
            'expiry_date',
            'purchase_date',
            'purchase_price',
            'category_id',
            'area_id',
            'room_id',
            'spot_id',
            'is_public',
        ];

        $this->assertEquals($fillable, $this->item->getFillable());
    }

    public function test_item_has_correct_casts(): void
    {
        $casts = [
            'id' => 'int',
            'expiry_date' => 'date',
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
        ];

        $this->assertEquals($casts, $this->item->getCasts());
    }

    public function test_item_has_thumbnail_url_attribute(): void
    {
        $this->assertContains('thumbnail_url', $this->item->getAppends());
    }

    public function test_get_thumbnail_url_returns_primary_image_thumbnail(): void
    {
        // Create primary image with thumbnail
        $primaryImage = ItemImage::factory()->create([
            'item_id' => $this->item->id,
            'is_primary' => true,
            'path' => 'items/1/test.jpg',
        ]);

        $thumbnailUrl = $this->item->thumbnail_url;

        $this->assertNotNull($thumbnailUrl);
        $this->assertStringContainsString('test-thumb.jpg', $thumbnailUrl);
    }

    public function test_get_thumbnail_url_returns_first_image_when_no_primary(): void
    {
        // Create non-primary image with thumbnail
        $firstImage = ItemImage::factory()->create([
            'item_id' => $this->item->id,
            'is_primary' => false,
            'path' => 'items/1/test.jpg',
        ]);

        $thumbnailUrl = $this->item->thumbnail_url;

        $this->assertNotNull($thumbnailUrl);
        $this->assertStringContainsString('test-thumb.jpg', $thumbnailUrl);
    }

    public function test_get_thumbnail_url_returns_null_when_no_images(): void
    {
        $thumbnailUrl = $this->item->thumbnail_url;

        $this->assertNull($thumbnailUrl);
    }

    public function test_get_thumbnail_url_returns_null_when_images_have_no_thumbnail(): void
    {
        // Create image with empty path (which means no thumbnail)
        ItemImage::factory()->create([
            'item_id' => $this->item->id,
            'is_primary' => true,
            'path' => '',
        ]);

        $thumbnailUrl = $this->item->thumbnail_url;

        $this->assertNull($thumbnailUrl);
    }

    public function test_to_searchable_array_returns_correct_data(): void
    {
        $searchableArray = $this->item->toSearchableArray();

        $expectedKeys = [
            'id',
            'name',
            'description',
            'status',
            'category_id',
            'is_public',
            'user_id',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $searchableArray);
        }

        $this->assertEquals($this->item->id, $searchableArray['id']);
        $this->assertEquals($this->item->name, $searchableArray['name']);
        $this->assertEquals($this->item->description, $searchableArray['description']);
        $this->assertEquals($this->item->status, $searchableArray['status']);
        $this->assertEquals($this->item->category_id, $searchableArray['category_id']);
        $this->assertEquals($this->item->is_public, $searchableArray['is_public']);
        $this->assertEquals($this->item->user_id, $searchableArray['user_id']);
    }

    public function test_search_scope_finds_items_by_name(): void
    {
        // Create items with different names
        Item::factory()->create(['name' => 'Test Item 1']);
        Item::factory()->create(['name' => 'Another Item']);
        Item::factory()->create(['name' => 'Test Item 2']);

        $results = Item::search('Test')->get();

        $this->assertEquals(2, $results->count());
        $this->assertTrue($results->every(function ($item) {
            return str_contains($item->name, 'Test');
        }));
    }

    public function test_search_scope_finds_items_by_description(): void
    {
        // Create items with different descriptions
        Item::factory()->create(['description' => 'This is a test description']);
        Item::factory()->create(['description' => 'Another description']);
        Item::factory()->create(['description' => 'Another test description']);

        $results = Item::search('test')->get();

        $this->assertEquals(2, $results->count());
        $this->assertTrue($results->every(function ($item) {
            return str_contains(strtolower($item->description), 'test');
        }));
    }

    public function test_search_scope_finds_items_by_name_or_description(): void
    {
        // Create items with test in name or description
        Item::factory()->create(['name' => 'Test Item', 'description' => 'Normal description']);
        Item::factory()->create(['name' => 'Normal Item', 'description' => 'Test description']);
        Item::factory()->create(['name' => 'Another Item', 'description' => 'Another description']);

        $results = Item::search('test')->get();

        $this->assertEquals(2, $results->count());
    }

    public function test_search_scope_returns_empty_when_no_matches(): void
    {
        Item::factory()->create(['name' => 'Item 1']);
        Item::factory()->create(['name' => 'Item 2']);

        $results = Item::search('nonexistent')->get();

        $this->assertEquals(0, $results->count());
    }

    public function test_local_scope_search_filters_name_or_description(): void
    {
        Item::factory()->create(['name' => 'local-scope alpha', 'description' => 'desc a']);
        Item::factory()->create(['name' => 'Plain Name', 'description' => 'Contains local-scope token']);
        Item::factory()->create(['name' => 'No Match', 'description' => 'none']);

        $results = Item::query()->search('local-scope')->get();

        $this->assertCount(2, $results);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(User::class, $this->item->user);
        $this->assertEquals($this->user->id, $this->item->user->id);
    }

    public function test_images_relationship(): void
    {
        // Create images for the item
        ItemImage::factory()->count(3)->create([
            'item_id' => $this->item->id,
        ]);

        $images = $this->item->images;

        $this->assertCount(3, $images);
        $this->assertTrue($images->every(function ($image) {
            return $image->item_id === $this->item->id;
        }));
    }

    public function test_primary_image_relationship(): void
    {
        // Create primary image
        $primaryImage = ItemImage::factory()->create([
            'item_id' => $this->item->id,
            'is_primary' => true,
        ]);

        $this->assertInstanceOf(ItemImage::class, $this->item->primaryImage);
        $this->assertEquals($primaryImage->id, $this->item->primaryImage->id);
    }

    public function test_category_relationship(): void
    {
        $category = ItemCategory::factory()->create();
        $this->item->update(['category_id' => $category->id]);

        $this->assertInstanceOf(ItemCategory::class, $this->item->category);
        $this->assertEquals($category->id, $this->item->category->id);
    }

    public function test_area_relationship(): void
    {
        $area = Area::factory()->create();
        $this->item->update(['area_id' => $area->id]);

        $this->assertInstanceOf(Area::class, $this->item->area);
        $this->assertEquals($area->id, $this->item->area->id);
    }

    public function test_room_relationship(): void
    {
        $room = Room::factory()->create();
        $this->item->update(['room_id' => $room->id]);

        $this->assertInstanceOf(Room::class, $this->item->room);
        $this->assertEquals($room->id, $this->item->room->id);
    }

    public function test_spot_relationship(): void
    {
        $spot = Spot::factory()->create();
        $this->item->update(['spot_id' => $spot->id]);

        $this->assertInstanceOf(Spot::class, $this->item->spot);
        $this->assertEquals($spot->id, $this->item->spot->id);
    }

    public function test_tags_relationship(): void
    {
        // Create tags
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        // Attach tags to item
        $this->item->tags()->attach([$tag1->id, $tag2->id]);

        $tags = $this->item->tags;

        $this->assertCount(2, $tags);
        $this->assertTrue($tags->contains($tag1));
        $this->assertTrue($tags->contains($tag2));
    }

    public function test_item_can_be_created_with_factory(): void
    {
        $item = Item::factory()->create();

        $this->assertInstanceOf(Item::class, $item);
        $this->assertDatabaseHas('thing_items', [
            'id' => $item->id,
        ]);
    }

    public function test_item_can_be_updated(): void
    {
        $newName = 'Updated Item Name';
        $this->item->update(['name' => $newName]);

        $this->assertEquals($newName, $this->item->fresh()->name);
    }

    public function test_item_can_be_deleted(): void
    {
        $itemId = $this->item->id;
        $this->item->delete();

        $this->assertDatabaseMissing('thing_items', [
            'id' => $itemId,
        ]);
    }

    public function test_item_has_correct_table_name(): void
    {
        $this->assertEquals('thing_items', $this->item->getTable());
    }

    public function test_item_uses_searchable_trait(): void
    {
        $traits = class_uses($this->item);
        $this->assertContains(\Laravel\Scout\Searchable::class, $traits);
    }
}
