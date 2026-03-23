<?php

namespace Tests\Unit\Services;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Services\File\ItemImageOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemImageOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemImageOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItemImageOrderService;
    }

    public function test_update_image_order()
    {
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create(['item_id' => $item->id, 'sort_order' => 1]);
        $image2 = ItemImage::factory()->create(['item_id' => $item->id, 'sort_order' => 2]);

        $imageOrder = [
            0 => $image2->id, // 第二个图片排第一
            1 => $image1->id, // 第一个图片排第二
        ];

        $this->service->updateImageOrder($imageOrder, $item);

        // 验证排序已更新
        $this->assertEquals(2, $image1->fresh()->sort_order);
        $this->assertEquals(1, $image2->fresh()->sort_order);
    }

    public function test_update_image_order_with_empty_array()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['item_id' => $item->id, 'sort_order' => 1]);

        $this->service->updateImageOrder([], $item);

        // 验证排序没有改变
        $this->assertEquals(1, $image->fresh()->sort_order);
    }

    public function test_update_image_order_only_updates_item_images()
    {
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        $image1 = ItemImage::factory()->create(['item_id' => $item1->id, 'sort_order' => 1]);
        $image2 = ItemImage::factory()->create(['item_id' => $item2->id, 'sort_order' => 1]);

        $imageOrder = [
            0 => $image1->id,
            1 => $image2->id,
        ];

        $this->service->updateImageOrder($imageOrder, $item1);

        // 验证只有 item1 的图片排序被更新
        $this->assertEquals(1, $image1->fresh()->sort_order);
        $this->assertEquals(1, $image2->fresh()->sort_order); // 没有改变
    }

    public function test_update_image_order_with_nonexistent_ids()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['item_id' => $item->id, 'sort_order' => 1]);

        $imageOrder = [
            0 => 999,
            1 => $image->id,
        ];

        $this->service->updateImageOrder($imageOrder, $item);

        // 验证现有图片的排序被更新
        $this->assertEquals(2, $image->fresh()->sort_order);
    }

    public function test_set_primary_image()
    {
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create(['item_id' => $item->id, 'is_primary' => true]);
        $image2 = ItemImage::factory()->create(['item_id' => $item->id, 'is_primary' => false]);

        $this->service->setPrimaryImage($image2->id, $item);

        // 验证主图已更改
        $this->assertFalse($image1->fresh()->is_primary);
        $this->assertTrue($image2->fresh()->is_primary);
    }

    public function test_set_primary_image_only_updates_item_images()
    {
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        $image1 = ItemImage::factory()->create(['item_id' => $item1->id, 'is_primary' => true]);
        $image2 = ItemImage::factory()->create(['item_id' => $item2->id, 'is_primary' => false]);

        $this->service->setPrimaryImage($image2->id, $item1);

        // 验证只有 item1 的图片被更新
        $this->assertFalse($image1->fresh()->is_primary);
        $this->assertFalse($image2->fresh()->is_primary); // 没有改变
    }

    public function test_set_primary_image_with_nonexistent_id()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['item_id' => $item->id, 'is_primary' => true]);

        $this->service->setPrimaryImage(999, $item);

        // 验证现有图片的主图状态被清除
        $this->assertFalse($image->fresh()->is_primary);
    }

    public function test_set_primary_image_clears_all_primary_images()
    {
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create(['item_id' => $item->id, 'is_primary' => true]);
        $image2 = ItemImage::factory()->create(['item_id' => $item->id, 'is_primary' => true]);
        $image3 = ItemImage::factory()->create(['item_id' => $item->id, 'is_primary' => false]);

        $this->service->setPrimaryImage($image3->id, $item);

        // 验证所有图片的主图状态被清除，然后只有指定图片被设置为主图
        $this->assertFalse($image1->fresh()->is_primary);
        $this->assertFalse($image2->fresh()->is_primary);
        $this->assertTrue($image3->fresh()->is_primary);
    }
}
