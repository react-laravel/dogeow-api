<?php

namespace Tests\Unit\Models\Thing;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemImageTest extends TestCase
{
    use RefreshDatabase;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = Item::factory()->create();
    }

    public function test_item_image_creation()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('thing_item_images', [
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);
    }

    public function test_item_image_relationship()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
        ]);

        $this->assertInstanceOf(Item::class, $image->item);
        $this->assertEquals($this->item->id, $image->item->id);
    }

    public function test_url_attribute()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
        ]);

        $expectedUrl = config('app.url') . '/storage/items/1/test.jpg';
        $this->assertEquals($expectedUrl, $image->url);
    }

    public function test_url_attribute_without_path()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => '',
        ]);

        $this->assertNull($image->url);
    }

    public function test_thumbnail_url_attribute()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
        ]);

        $expectedUrl = config('app.url') . '/storage/items/1/test-thumb.jpg';
        $this->assertEquals($expectedUrl, $image->thumbnail_url);
    }

    public function test_thumbnail_url_attribute_without_path()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => '',
        ]);

        $this->assertNull($image->thumbnail_url);
    }

    public function test_thumbnail_url_with_different_extensions()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.png',
        ]);

        $expectedUrl = config('app.url') . '/storage/items/1/test-thumb.png';
        $this->assertEquals($expectedUrl, $image->thumbnail_url);
    }

    public function test_thumbnail_path_attribute()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
        ]);

        $expectedPath = '/storage/items/1/test-thumb.jpg';
        $this->assertEquals($expectedPath, $image->thumbnail_path);
    }

    public function test_thumbnail_path_attribute_without_path()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => '',
        ]);

        $this->assertNull($image->thumbnail_path);
    }

    public function test_is_primary_cast()
    {
        $primaryImage = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/primary.jpg',
            'is_primary' => true,
        ]);

        $regularImage = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/regular.jpg',
            'is_primary' => false,
        ]);

        $this->assertTrue($primaryImage->is_primary);
        $this->assertFalse($regularImage->is_primary);
        $this->assertIsBool($primaryImage->is_primary);
        $this->assertIsBool($regularImage->is_primary);
    }

    public function test_item_image_factory()
    {
        $image = ItemImage::factory()->create([
            'item_id' => $this->item->id,
        ]);

        $this->assertInstanceOf(ItemImage::class, $image);
        $this->assertEquals($this->item->id, $image->item_id);
        $this->assertNotEmpty($image->path);
    }

    public function test_item_image_with_complex_path()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/123/subfolder/image-name.jpg',
        ]);

        $expectedUrl = config('app.url') . '/storage/items/123/subfolder/image-name.jpg';
        $expectedThumbUrl = config('app.url') . '/storage/items/123/subfolder/image-name-thumb.jpg';
        $expectedThumbPath = '/storage/items/123/subfolder/image-name-thumb.jpg';

        $this->assertEquals($expectedUrl, $image->url);
        $this->assertEquals($expectedThumbUrl, $image->thumbnail_url);
        $this->assertEquals($expectedThumbPath, $image->thumbnail_path);
    }

    public function test_item_image_sort_order()
    {
        $image1 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/image1.jpg',
            'sort_order' => 1,
        ]);

        $image2 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/image2.jpg',
            'sort_order' => 2,
        ]);

        $this->assertEquals(1, $image1->sort_order);
        $this->assertEquals(2, $image2->sort_order);
    }

    public function test_item_image_fillable_attributes()
    {
        $data = [
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
            'sort_order' => 5,
            'is_primary' => false,
        ];

        $image = ItemImage::create($data);

        $this->assertEquals($data['item_id'], $image->item_id);
        $this->assertEquals($data['path'], $image->path);
        $this->assertEquals($data['sort_order'], $image->sort_order);
        $this->assertEquals($data['is_primary'], $image->is_primary);
    }

    public function test_item_image_appends_attributes()
    {
        $image = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/1/test.jpg',
        ]);

        $array = $image->toArray();

        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('thumbnail_url', $array);
        $this->assertArrayHasKey('thumbnail_path', $array);
    }
}
