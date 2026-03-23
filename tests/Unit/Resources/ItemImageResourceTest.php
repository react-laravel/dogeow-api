<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ItemImageResource;
use App\Models\Thing\ItemImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemImageResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_transforms_item_image_to_array()
    {
        $itemImage = ItemImage::factory()->create([
            'path' => 'items/test-image.jpg',
        ]);

        $resource = new ItemImageResource($itemImage);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'thumbnail_path' => $itemImage->thumbnail_path,
        ], $array);
    }

    #[Test]
    public function it_handles_null_thumbnail_path()
    {
        $itemImage = ItemImage::factory()->create([
            'path' => '',
        ]);

        $resource = new ItemImageResource($itemImage);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'thumbnail_path' => null,
        ], $array);
    }

    #[Test]
    public function it_handles_empty_thumbnail_path()
    {
        $itemImage = ItemImage::factory()->create([
            'path' => '',
        ]);

        $resource = new ItemImageResource($itemImage);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'thumbnail_path' => null,
        ], $array);
    }
}
