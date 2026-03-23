<?php

namespace App\Services\File;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use Illuminate\Support\Facades\Storage;

class ItemImageManagementService
{
    /**
     * 删除指定 ID 的图片及其文件
     *
     * @param  array  $imageIdsToDelete  要删除的图片 ID 数组
     * @param  Item  $item  要删除图片的物品
     */
    public function deleteImagesByIds(array $imageIdsToDelete, Item $item): void
    {
        $imagesToDelete = ItemImage::whereIn('id', $imageIdsToDelete)
            ->where('item_id', $item->id)
            ->get();

        foreach ($imagesToDelete as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }

    /**
     * 删除与物品关联的所有图片(文件和记录)
     * 通常在删除物品本身时使用
     *
     * @param  Item  $item  要删除图片的物品
     */
    public function deleteAllItemImages(Item $item): void
    {
        $images = $item->images;
        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
        }
        ItemImage::where('item_id', $item->id)->delete();
    }
}
