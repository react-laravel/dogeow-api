<?php

namespace App\Services\File;

use App\Jobs\GenerateThumbnailForItemImageJob;
use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    public function __construct(
        private readonly RmbgStatusService $rmbgStatusService,
        private readonly RmbgItemImageLinkerService $rmbgItemImageLinker,
    ) {}

    /**
     * 处理上传的图片，保存并创建缩略图
     *
     * @param  array  $uploadedImages  上传的文件数组
     * @param  Item  $item  关联的物品
     * @return int 成功处理的图片数量
     */
    public function processUploadedImages(array $uploadedImages, Item $item): int
    {
        $sortOrder = ItemImage::where('item_id', $item->id)->max('sort_order') ?? 0;
        $successCount = 0;

        // 确保存储目录存在
        $dirPath = storage_path('app/public/items/' . $item->id);
        if (! file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        foreach ($uploadedImages as $image) {
            try {
                $sortOrder++;
                $filename = $image->getClientOriginalName();
                $relativePath = 'items/' . $item->id . '/' . $filename;

                if ($image->move($dirPath, $filename)) {
                    $isPrimary = ($sortOrder === 1 && ! ItemImage::where('item_id', $item->id)
                        ->where('is_primary', true)->exists());

                    $itemImage = ItemImage::create([
                        'item_id' => $item->id,
                        'path' => $relativePath,
                        'is_primary' => $isPrimary,
                        'sort_order' => $sortOrder,
                    ]);

                    GenerateThumbnailForItemImageJob::dispatch($itemImage);
                    $successCount++;

                } else {
                    throw new \Exception('移动图片文件失败');
                }
            } catch (\Exception $e) {
                Log::error('图片处理错误: ' . $e->getMessage(), [
                    'file' => $image->getClientOriginalName(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $successCount;
    }

    /**
     * 处理来自'uploads'目录的图片路径，将它们移动到物品目录并创建缩略图
     *
     * @param  array  $imagePaths  图片路径数组(例如：'uploads/tempfile.jpg')
     * @param  Item  $item  关联的物品
     */
    public function processImagePaths(array $imagePaths, Item $item): void
    {
        $dirPath = storage_path('app/public/items/' . $item->id);
        if (! file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $currentMaxSortOrder = ItemImage::where('item_id', $item->id)->max('sort_order') ?? 0;

        foreach ($imagePaths as $originPath) {
            if (! str_starts_with($originPath, 'uploads/')) {
                continue;
            }

            $originAbsPath = storage_path('app/public/' . $originPath);
            if (! file_exists($originAbsPath)) {
                continue;
            }

            $filename = substr($originPath, strrpos($originPath, '/') + 1);
            $companionFilenames = $this->companionFilenames($filename);
            $itemPath = 'items/' . $item->id . '/' . $filename;
            $absItemPath = $dirPath . '/' . $filename;

            $thumbFilename = $companionFilenames['thumb'];
            $thumbOriginPath = dirname($originAbsPath) . '/' . $thumbFilename;
            $thumbItemPath = 'items/' . $item->id . '/' . $thumbFilename;
            $absThumbPath = $dirPath . '/' . $thumbFilename;

            $originFilename = $companionFilenames['origin'];
            $originCompanionPath = dirname($originAbsPath) . '/' . $originFilename;
            $absOriginCompanionPath = $dirPath . '/' . $originFilename;

            if (rename($originAbsPath, $absItemPath)) {
                if ($thumbFilename !== $filename && file_exists($thumbOriginPath)) {
                    rename($thumbOriginPath, $absThumbPath);
                }
                if ($originFilename !== $filename && file_exists($originCompanionPath)) {
                    rename($originCompanionPath, $absOriginCompanionPath);
                }

                $currentMaxSortOrder++;
                $isPrimary = ($currentMaxSortOrder === 1 && ! ItemImage::where('item_id', $item->id)->where('is_primary', true)->exists());

                $itemImage = ItemImage::create([
                    'item_id' => $item->id,
                    'path' => $itemPath,
                    'is_primary' => $isPrimary,
                    'sort_order' => $currentMaxSortOrder,
                ]);

                $this->linkPendingRmbgIfNeeded($originPath, $itemImage->id);

                GenerateThumbnailForItemImageJob::dispatch($itemImage);

            } else {
                Log::error('从 uploads 移动图片文件失败', ['origin_path' => $originPath, 'destination' => $absItemPath]);
            }
        }
    }

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
            Storage::disk('public')->delete($this->imagePathsForDeletion($image));
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
            /** @var ItemImage $image */
            Storage::disk('public')->delete($this->imagePathsForDeletion($image));
        }
        ItemImage::where('item_id', $item->id)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function imagePathsForDeletion(ItemImage $image): array
    {
        $path = $image->path;
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_BASENAME);
        $companionFilenames = $this->companionFilenames($filename);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return array_values(array_unique([
            $path,
            $dirname . '/' . $companionFilenames['thumb'],
            $dirname . '/' . $companionFilenames['origin'],
        ]));
    }

    /**
     * @return array{thumb: string, origin: string}
     */
    private function companionFilenames(string $filename): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        return [
            'thumb' => $baseName . '-thumb.' . $extension,
            'origin' => $baseName . '-origin.' . $extension,
        ];
    }

    private function linkPendingRmbgIfNeeded(string $uploadPath, int $itemImageId): void
    {
        $status = $this->rmbgStatusService->get($uploadPath);
        $rmbgStatus = is_array($status) ? ($status['status'] ?? null) : null;

        if (! in_array($rmbgStatus, ['pending', 'processing'], true)) {
            return;
        }

        $this->rmbgItemImageLinker->link($uploadPath, $itemImageId);
    }
}
