<?php

namespace App\Jobs;

use App\Services\File\ImageProcessingService;
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
    ): void {
        $rmbgStatusService->setProcessing($this->compressedPath);

        $apiUrl = config('services.rmbg.url');
        if (empty($apiUrl)) {
            $rmbgStatusService->setFailed($this->compressedPath, '去背景服务未配置');

            return;
        }

        try {
            $response = Http::timeout((int) config('services.rmbg.timeout', 120))
                ->post($apiUrl, [
                    'image_url' => $this->originUrl,
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
