<?php

namespace App\Services\Thing;

use App\Models\Thing\Item;
use App\Services\File\ImageUploadService;
use Illuminate\Http\Request;

class ItemService
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService
    ) {}

    /**
     * 处理物品图片(创建时)
     */
    public function processItemImages(Request $request, Item $item): void
    {
        if ($request->hasFile('images')) {
            $this->imageUploadService->processUploadedImages($request->file('images'), $item);
        }

        if ($request->has('image_paths')) {
            $this->imageUploadService->processImagePaths($request->image_paths, $item);
        }
    }

    /**
     * 处理物品图片更新
     */
    public function processItemImageUpdates(Request $request, Item $item): void
    {
        $this->syncImagesByIds($request, $item);
        $this->processImagePathsUpdate($request, $item);
        $this->processImageOrderUpdate($request, $item);
        $this->processPrimaryImageUpdate($request, $item);
        $this->processImageDeletes($request, $item);
    }

    /**
     * 同步图片集合(保留 image_ids，其余删除)
     */
    private function syncImagesByIds(Request $request, Item $item): void
    {
        if (! $request->has('image_ids')) {
            return;
        }

        $keepIds = $request->input('image_ids', []);
        $allIds = $item->images()->pluck('id')->toArray();
        $deleteIds = array_diff($allIds, $keepIds);

        if (! empty($deleteIds)) {
            $this->imageUploadService->deleteImagesByIds($deleteIds, $item);
        }
    }

    /**
     * 处理图片路径更新
     */
    private function processImagePathsUpdate(Request $request, Item $item): void
    {
        if ($request->has('image_paths')) {
            $this->imageUploadService->processImagePaths($request->image_paths, $item);
        }
    }

    /**
     * 处理图片排序更新
     */
    private function processImageOrderUpdate(Request $request, Item $item): void
    {
        if ($request->has('image_order')) {
            $this->imageUploadService->updateImageOrder($request->image_order, $item);
        }
    }

    /**
     * 处理主图更新
     */
    private function processPrimaryImageUpdate(Request $request, Item $item): void
    {
        if ($request->has('primary_image_id')) {
            $this->imageUploadService->setPrimaryImage($request->primary_image_id, $item);
        }
    }

    /**
     * 处理图片删除
     */
    private function processImageDeletes(Request $request, Item $item): void
    {
        if ($request->has('delete_images')) {
            $this->imageUploadService->deleteImagesByIds($request->delete_images, $item);
        }
    }

    /**
     * 处理标签
     */
    public function handleTags(Request $request, Item $item): void
    {
        if ($request->has('tags')) {
            $item->tags()->sync($request->tags ?? []);
        } elseif ($request->has('tag_ids')) {
            $item->tags()->sync($request->tag_ids ?? []);
        }
    }
}
