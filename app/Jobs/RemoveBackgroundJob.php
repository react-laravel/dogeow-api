<?php

namespace App\Jobs;

use App\Models\Thing\ItemImage;
use App\Services\File\ImageProcessingService;
use App\Services\File\RmbgItemImageLinkerService;
use App\Services\File\RmbgStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RemoveBackgroundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 150;

    public function __construct(
        public int $userId,
        public string $compressedPath,
        public string $originPath,
        public string $originUrl,
    ) {}

    public function handle(
        RmbgStatusService $rmbgStatusService,
        ImageProcessingService $imageProcessingService,
        RmbgItemImageLinkerService $rmbgItemImageLinker,
    ): void {
        $rmbgStatusService->setProcessing($this->compressedPath);

        $apiUrl = config('services.rmbg.url');
        if (empty($apiUrl)) {
            $rmbgStatusService->setFailed($this->compressedPath, '去背景服务未配置');

            return;
        }

        try {
            $linkedItemImageId = $rmbgItemImageLinker->getItemImageIdForUploadPath($this->compressedPath);
            $originUrl = $this->resolveOriginUrl($linkedItemImageId);
            $response = Http::timeout((int) config('services.rmbg.timeout', 120))
                ->post($apiUrl, [
                    'image_url' => $originUrl,
                    'bg' => 'transparent',
                ]);

            if (! $response->successful()) {
                $rmbgStatusService->setFailed(
                    $this->compressedPath,
                    '去背景服务返回错误: HTTP ' . $response->status()
                );

                return;
            }

            $pngBytes = $response->body();
            if ($pngBytes === '' || strlen($pngBytes) < 100) {
                $rmbgStatusService->setFailed($this->compressedPath, '去背景结果无效');

                return;
            }

            // 物品可能在排队或请求期间才创建并关联，需再次读取
            $linkedItemImageId = $rmbgItemImageLinker->getItemImageIdForUploadPath($this->compressedPath);

            if ($linkedItemImageId !== null) {
                $result = $this->applyToLinkedItemImage(
                    $linkedItemImageId,
                    $pngBytes,
                    $imageProcessingService,
                );

                if ($result === null) {
                    $rmbgStatusService->setFailed($this->compressedPath, '关联的物品图片不存在');

                    return;
                }

                $rmbgStatusService->setDone($this->compressedPath, array_merge(
                    ['status' => 'done'],
                    $result,
                    [
                        'origin_path' => $result['origin_path'] ?? $this->originPath,
                        'origin_url' => $result['origin_url'] ?? $originUrl,
                    ],
                ));
                $rmbgItemImageLinker->unlink($this->compressedPath);

                return;
            }

            $disk = Storage::disk('public');
            $newCompressedPath = $this->replaceExtension($this->compressedPath, 'png');
            $disk->put($newCompressedPath, $pngBytes);

            if ($newCompressedPath !== $this->compressedPath) {
                $this->deleteCompanionFiles($disk, $this->compressedPath);
            }

            $absolutePath = $disk->path($newCompressedPath);
            $thumbResult = $imageProcessingService->createThumbnailFromCompressed($absolutePath);
            if (empty($thumbResult['success'])) {
                Log::warning('去背景后缩略图生成失败', [
                    'path' => $newCompressedPath,
                    'message' => $thumbResult['message'] ?? null,
                ]);
            }

            $urls = $this->buildPublicUrls($newCompressedPath);

            $rmbgStatusService->setDone($this->compressedPath, [
                'path' => $newCompressedPath,
                'url' => $urls['url'],
                'thumbnail_url' => $urls['thumbnail_url'],
                'thumbnail_path' => $urls['thumbnail_path'],
                'origin_path' => $this->originPath,
                'origin_url' => $this->originUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('去背景任务失败', [
                'path' => $this->compressedPath,
                'error' => $e->getMessage(),
            ]);
            $rmbgStatusService->setFailed($this->compressedPath, $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        app(RmbgStatusService::class)->setFailed(
            $this->compressedPath,
            $exception->getMessage()
        );
    }

    private function resolveOriginUrl(?int $linkedItemImageId): string
    {
        if ($linkedItemImageId === null) {
            return $this->originUrl;
        }

        $itemImage = ItemImage::query()->find($linkedItemImageId);
        if ($itemImage === null) {
            return $this->originUrl;
        }

        $originPath = $this->findOriginCompanionPath($itemImage->path);
        if ($originPath !== null) {
            return url('storage/' . $originPath);
        }

        if (Storage::disk('public')->exists($itemImage->path)) {
            return url('storage/' . $itemImage->path);
        }

        return $this->originUrl;
    }

    /**
     * @return array<string, string>|null
     */
    private function applyToLinkedItemImage(
        int $itemImageId,
        string $pngBytes,
        ImageProcessingService $imageProcessingService,
    ): ?array {
        $itemImage = ItemImage::query()->find($itemImageId);
        if ($itemImage === null) {
            return null;
        }

        $disk = Storage::disk('public');
        $oldPath = $itemImage->path;
        $dir = pathinfo($oldPath, PATHINFO_DIRNAME);
        $base = pathinfo($oldPath, PATHINFO_FILENAME);
        $newPath = $dir . '/' . $base . '.png';

        $disk->put($newPath, $pngBytes);

        foreach ($this->companionPathsForDeletion($oldPath) as $pathToDelete) {
            if ($disk->exists($pathToDelete)) {
                $disk->delete($pathToDelete);
            }
        }

        $absolutePath = $disk->path($newPath);
        $thumbResult = $imageProcessingService->createThumbnailFromCompressed($absolutePath);
        if (empty($thumbResult['success'])) {
            Log::warning('去背景后缩略图生成失败', [
                'path' => $newPath,
                'message' => $thumbResult['message'] ?? null,
            ]);
        }

        $itemImage->update(['path' => $newPath]);

        $originPath = $this->findOriginCompanionPath($newPath);
        $urls = $this->buildPublicUrls($newPath);

        return [
            'path' => $newPath,
            'url' => $urls['url'],
            'thumbnail_url' => $urls['thumbnail_url'],
            'thumbnail_path' => $urls['thumbnail_path'],
            'origin_path' => $originPath ?? $this->originPath,
            'origin_url' => $originPath !== null ? url('storage/' . $originPath) : $this->originUrl,
        ];
    }

    private function findOriginCompanionPath(string $displayPath): ?string
    {
        $disk = Storage::disk('public');
        $dirname = pathinfo($displayPath, PATHINFO_DIRNAME);
        $filename = pathinfo($displayPath, PATHINFO_FILENAME);
        $extension = pathinfo($displayPath, PATHINFO_EXTENSION);
        $originPath = $dirname . '/' . $filename . '-origin.' . $extension;

        if ($disk->exists($originPath)) {
            return $originPath;
        }

        return $disk->exists($displayPath) ? $displayPath : null;
    }

    /**
     * @return array<int, string>
     */
    private function companionPathsForDeletion(string $path): array
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_BASENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $baseName = pathinfo($path, PATHINFO_FILENAME);

        return array_values(array_unique([
            $path,
            $dirname . '/' . $baseName . '-thumb.' . $extension,
            $dirname . '/' . $baseName . '-origin.' . $extension,
        ]));
    }

    private function replaceExtension(string $path, string $extension): string
    {
        $info = pathinfo($path);
        $dir = $info['dirname'] ?? '';
        $filename = $info['filename'] ?? '';

        if ($dir === '' || $dir === '.') {
            return $filename . '.' . $extension;
        }

        return $dir . '/' . $filename . '.' . $extension;
    }

    /**
     * @param  Filesystem  $disk
     */
    private function deleteCompanionFiles($disk, string $compressedPath): void
    {
        $info = pathinfo($compressedPath);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '-thumb.' . ($info['extension'] ?? 'jpg');

        if ($disk->exists($compressedPath)) {
            $disk->delete($compressedPath);
        }
        if ($disk->exists($thumbPath)) {
            $disk->delete($thumbPath);
        }
    }

    /**
     * @return array{url: string, thumbnail_url: string, thumbnail_path: string}
     */
    private function buildPublicUrls(string $path): array
    {
        $info = pathinfo($path);
        $thumbPath = $info['dirname'] . '/' . $info['filename'] . '-thumb.' . ($info['extension'] ?? 'png');

        return [
            'url' => url('storage/' . $path),
            'thumbnail_url' => url('storage/' . $thumbPath),
            'thumbnail_path' => $thumbPath,
        ];
    }
}
