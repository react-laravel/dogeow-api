<?php

namespace App\Jobs;

use App\Models\Thing\ItemImage;
use Dogeow\PhpHelpers\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GenerateThumbnailForItemImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    protected ItemImage $itemImage;

    protected int $thumbnailWidth;

    protected int $thumbnailHeight;

    protected string $thumbnailSuffix;

    /**
     * 创建新的任务实例
     */
    public function __construct(
        ItemImage $itemImage,
        int $thumbnailWidth = 200,
        int $thumbnailHeight = 200,
        string $thumbnailSuffix = '-thumb'
    ) {
        $this->itemImage = $itemImage;
        $this->thumbnailWidth = $thumbnailWidth;
        $this->thumbnailHeight = $thumbnailHeight;
        $this->thumbnailSuffix = $thumbnailSuffix;
    }

    /**
     * 执行任务
     */
    public function handle(): void
    {
        // 刷新模型获取最新数据
        $this->itemImage->refresh();

        if (! $this->validateOriginalImage()) {
            return;
        }

        $thumbnailPath = $this->generateThumbnailPath();

        // 如果缩略图已存在且比原图新，则跳过
        if ($this->thumbnailExistsAndIsNewer($thumbnailPath)) {
            Log::info("缩略图已存在且是最新的，ItemImage ID: {$this->itemImage->id}");

            return;
        }

        $this->generateThumbnail($thumbnailPath);
    }

    /**
     * 验证原始图片是否存在且可访问
     */
    protected function validateOriginalImage(): bool
    {
        if (! $this->itemImage->path) {
            Log::warning("ItemImage ID: {$this->itemImage->id} 未设置路径");

            return false;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($this->itemImage->path)) {
            Log::error("找不到原始图片，ItemImage ID: {$this->itemImage->id}, 路径: {$this->itemImage->path}");

            return false;
        }

        // 检查文件是否可读且未损坏
        $originalPath = $disk->path($this->itemImage->path);
        if (! File::isValid($originalPath)) {
            Log::error("原始图片不可读或为空，ItemImage ID: {$this->itemImage->id}");

            return false;
        }

        return true;
    }

    /**
     * 生成缩略图文件路径
     */
    protected function generateThumbnailPath(): string
    {
        $pathInfo = pathinfo($this->itemImage->path);
        $extension = $pathInfo['extension'] ?? 'jpg';
        $thumbnailFilename = $pathInfo['filename'] . $this->thumbnailSuffix . '.' . $extension;

        return $pathInfo['dirname'] . '/' . $thumbnailFilename;
    }

    /**
     * 检查缩略图是否存在且比原图新
     */
    protected function thumbnailExistsAndIsNewer(string $thumbnailPath): bool
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($thumbnailPath)) {
            return false;
        }

        $originalModified = $disk->lastModified($this->itemImage->path);
        $thumbnailModified = $disk->lastModified($thumbnailPath);

        return $thumbnailModified >= $originalModified;
    }

    /**
     * 生成缩略图
     */
    protected function generateThumbnail(string $thumbnailPath): void
    {
        $disk = Storage::disk('public');
        $originalFullPath = $disk->path($this->itemImage->path);
        $thumbnailFullPath = $disk->path($thumbnailPath);

        try {
            // 确保目录存在
            File::ensureDirectoryExists(dirname($thumbnailFullPath));

            $manager = new ImageManager(new Driver);
            $image = $manager->read($originalFullPath);

            // 获取原始尺寸用于日志记录
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            // 只有当图片大于缩略图尺寸时才调整大小
            if ($originalWidth > $this->thumbnailWidth || $originalHeight > $this->thumbnailHeight) {
                $image->cover($this->thumbnailWidth, $this->thumbnailHeight);
            }

            // 保存时进行质量优化
            $image->save($thumbnailFullPath, quality: 85);

            Log::info("成功生成缩略图，ItemImage ID: {$this->itemImage->id}", [
                'original_path' => $this->itemImage->path,
                'thumbnail_path' => $thumbnailPath,
                'original_size' => "{$originalWidth}x{$originalHeight}",
                'thumbnail_size' => "{$this->thumbnailWidth}x{$this->thumbnailHeight}",
                'file_size' => File::getFormattedSize($thumbnailFullPath),
            ]);

        } catch (\Exception $e) {
            Log::error("缩略图生成失败，ItemImage ID: {$this->itemImage->id}", [
                'error' => $e->getMessage(),
                'original_path' => $this->itemImage->path,
                'thumbnail_path' => $thumbnailPath,
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 清理部分生成的文件
            if (file_exists($thumbnailFullPath)) {
                unlink($thumbnailFullPath);
            }

            throw $e; // 重新抛出异常以触发重试机制
        }
    }

    /**
     * 处理任务失败
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("缩略图生成任务永久失败，ItemImage ID: {$this->itemImage->id}", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'path' => $this->itemImage->path,
        ]);
    }
}
