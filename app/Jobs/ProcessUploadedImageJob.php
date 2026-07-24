<?php

namespace App\Jobs;

use App\Services\File\ImageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUploadedImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public int $maxExceptions = 3;

    /**
     * @param  array{user_id?: int, compressed_relative_path?: string, origin_relative_path?: string, origin_url?: string, remove_bg?: bool}|null  $rmbgContext
     */
    public function __construct(
        public readonly string $originPath,
        public readonly string $compressedPath,
        public readonly ?array $rmbgContext = null,
    ) {}

    public function handle(ImageProcessingService $imageProcessingService): void
    {
        $result = $imageProcessingService->processImage($this->originPath, $this->compressedPath);

        if (! ($result['success'] ?? false)) {
            Log::error('ProcessUploadedImageJob failed', [
                'origin_path' => $this->originPath,
                'compressed_path' => $this->compressedPath,
                'message' => $result['message'] ?? 'unknown',
            ]);

            throw new \RuntimeException($result['message'] ?? 'Image processing failed');
        }

        if (($this->rmbgContext['remove_bg'] ?? false) === true) {
            RemoveBackgroundJob::dispatch(
                (int) ($this->rmbgContext['user_id'] ?? 0),
                (string) ($this->rmbgContext['compressed_relative_path'] ?? ''),
                (string) ($this->rmbgContext['origin_relative_path'] ?? ''),
                (string) ($this->rmbgContext['origin_url'] ?? ''),
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessUploadedImageJob permanently failed', [
            'origin_path' => $this->originPath,
            'compressed_path' => $this->compressedPath,
            'error' => $exception->getMessage(),
        ]);
    }
}
