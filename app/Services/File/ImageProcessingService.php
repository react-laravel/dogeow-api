<?php

namespace App\Services\File;

use App\Services\BaseService;
use App\Utils\Constants;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class ImageProcessingService extends BaseService
{
    private ImageManager $manager;

    // 图片尺寸配置
    private function getThumbnailSize(): int
    {
        return Constants::thumbnailSize();
    }

    private function getCompressedMaxSize(): int
    {
        return Constants::compressedMaxSize();
    }

    private function getQualityCompressed(): int
    {
        return Constants::image('quality')['compressed'];
    }

    private function getQualityThumbnail(): int
    {
        return Constants::image('quality')['thumbnail'];
    }

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * 处理图片(生成缩略图和压缩图)
     */
    public function processImage(string $originPath, string $compressedPath): array
    {
        try {
            if (! file_exists($originPath)) {
                $this->logError('Original image file not found', [
                    'path' => $originPath,
                ]);

                return $this->error('Original image file not found');
            }

            if ($this->isHeicImage($originPath)) {
                return $this->processHeicWithPython($originPath, $compressedPath);
            }

            $img = $this->manager->read($originPath);
            $dimensions = [
                'width' => $img->width(),
                'height' => $img->height(),
            ];

            // 生成缩略图
            $thumbnailResult = $this->createThumbnail($originPath, $compressedPath);
            if (! $thumbnailResult['success']) {
                return $thumbnailResult;
            }

            // 生成压缩图
            $compressedResult = $this->createCompressedImage($originPath, $compressedPath);
            if (! $compressedResult['success']) {
                return $compressedResult;
            }

            $this->logInfo('Image processed successfully', [
                'original_path' => $originPath,
                'dimensions' => $dimensions,
            ]);

            return $this->success($dimensions, 'Image processed successfully');

        } catch (\Throwable $e) {
            return $this->handleException($e, 'process image');
        }
    }

    /**
     * 创建缩略图
     */
    private function createThumbnail(string $originPath, string $compressedPath): array
    {
        try {
            $thumbnail = $this->manager->read($originPath);
            $originalWidth = $thumbnail->width();
            $originalHeight = $thumbnail->height();

            // 如果原图尺寸已经很小，则不需要缩放
            $thumbnailSize = $this->getThumbnailSize();
            if ($this->shouldSkipResize($originalWidth, $originalHeight, $thumbnailSize)) {
                $thumbnail = $thumbnail; // 保持原尺寸
            } else {
                $thumbnail = $this->resizeImage($thumbnail, $thumbnailSize);
            }

            $thumbnailPath = $this->getThumbnailPath($compressedPath);
            $thumbnail->save($thumbnailPath, quality: $this->getQualityThumbnail());

            return $this->success(['thumbnail_path' => $thumbnailPath]);

        } catch (\Throwable $e) {
            return $this->handleException($e, 'create thumbnail');
        }
    }

    /**
     * 创建压缩图
     */
    private function createCompressedImage(string $originPath, string $compressedPath): array
    {
        try {
            $compressed = $this->manager->read($originPath);
            $originalWidth = $compressed->width();
            $originalHeight = $compressed->height();

            // 如果需要压缩尺寸
            $compressedMaxSize = $this->getCompressedMaxSize();
            if ($originalWidth > $compressedMaxSize || $originalHeight > $compressedMaxSize) {
                $compressed = $this->resizeImage($compressed, $compressedMaxSize);
            }

            $compressed->save($compressedPath, quality: $this->getQualityCompressed());

            return $this->success(['compressed_path' => $compressedPath]);

        } catch (\Throwable $e) {
            return $this->handleException($e, 'create compressed image');
        }
    }

    /**
     * 调整图片尺寸(保持宽高比)
     */
    private function resizeImage($image, int $maxSize)
    {
        $width = $image->width();
        $height = $image->height();

        if ($width >= $height) {
            return $image->scale(width: $maxSize);
        } else {
            return $image->scale(height: $maxSize);
        }
    }

    /**
     * 判断是否应该跳过调整尺寸
     */
    private function shouldSkipResize(int $width, int $height, int $targetSize): bool
    {
        return $width <= $targetSize && $height <= $targetSize;
    }

    private function isHeicImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['heic', 'heif'], true);
    }

    private function processHeicWithPython(string $originPath, string $compressedPath): array
    {
        $script = base_path('scripts/heic-convert.py');
        if (! is_file($script)) {
            $script = '/opt/dogeow-heic-convert.py';
        }

        $pythonPath = env('HEIC_CONVERTER_PYTHONPATH', '/opt/dogeow-heic-python');
        $thumbnailPath = $this->getThumbnailPath($compressedPath);

        if (! is_file($script) || ! is_dir($pythonPath)) {
            return $this->error('HEIC converter is not installed');
        }

        $command = sprintf(
            'PYTHONPATH=%s /usr/bin/python3 %s %s %s %s %d %d %d %d 2>&1',
            escapeshellarg($pythonPath),
            escapeshellarg($script),
            escapeshellarg($originPath),
            escapeshellarg($compressedPath),
            escapeshellarg($thumbnailPath),
            $this->getCompressedMaxSize(),
            $this->getThumbnailSize(),
            $this->getQualityCompressed(),
            $this->getQualityThumbnail()
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->logError('HEIC conversion failed', [
                'origin_path' => $originPath,
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);

            return $this->error('HEIC conversion failed');
        }

        $payload = json_decode(end($output) ?: '', true);
        if (! is_array($payload) || ! isset($payload['width'], $payload['height'])) {
            return $this->error('HEIC conversion returned invalid output');
        }

        $dimensions = [
            'width' => (int) $payload['width'],
            'height' => (int) $payload['height'],
        ];

        $this->logInfo('HEIC image processed successfully', [
            'original_path' => $originPath,
            'compressed_path' => $compressedPath,
            'thumbnail_path' => $thumbnailPath,
            'dimensions' => $dimensions,
        ]);

        return $this->success($dimensions, 'Image processed successfully');
    }

    /**
     * 获取缩略图路径
     */
    private function getThumbnailPath(string $compressedPath): string
    {
        $pathInfo = pathinfo($compressedPath);

        return $pathInfo['dirname'] . DIRECTORY_SEPARATOR
            . $pathInfo['filename'] . '-thumb.'
            . $pathInfo['extension'];
    }

    /**
     * 获取图片信息
     */
    public function getImageInfo(string $imagePath): array
    {
        try {
            if (! file_exists($imagePath)) {
                return $this->error('Image file not found');
            }

            $img = $this->manager->read($imagePath);

            return $this->success([
                'width' => $img->width(),
                'height' => $img->height(),
                'size' => filesize($imagePath),
                'mime_type' => mime_content_type($imagePath),
            ]);

        } catch (\Throwable $e) {
            return $this->handleException($e, 'get image info');
        }
    }
}
