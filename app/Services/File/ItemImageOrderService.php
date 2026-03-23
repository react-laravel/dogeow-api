<?php

namespace App\Services\File;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;

class ItemImageOrderService
{
    /**
     * 更新物品图片的排序
     *
     * @param  array  $imageOrder  排序数组，键为排序顺序(从 0 开始)，值为图片 ID
     * @param  Item  $item  要重新排序的物品
     */
    public function updateImageOrder(array $imageOrder, Item $item): void
    {
        foreach ($imageOrder as $order => $imageId) {
            ItemImage::where('id', $imageId)
                ->where('item_id', $item->id)
                ->update(['sort_order' => $order + 1]);
        }
    }

    /**
     * 设置物品的主图
     *
     * @param  int  $primaryImageId  要设置为主图的图片 ID
     * @param  Item  $item  要设置主图的物品
     */
    public function setPrimaryImage(int $primaryImageId, Item $item): void
    {
        ItemImage::where('item_id', $item->id)
            ->update(['is_primary' => false]);

        ItemImage::where('id', $primaryImageId)
            ->where('item_id', $item->id)
            ->update(['is_primary' => true]);
    }
}
