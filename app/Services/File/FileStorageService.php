<?php

namespace App\Services\File;

use App\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService extends BaseService
{
    private function getAllowedExtensions(): array
    {
        return \App\Utils\Constants::allowedExtensions();
    }

    private function getMaxFileSize(): int
    {
        return \App\Utils\Constants::maxFileSize();
    }

    private function getDefaultExtension(): string
    {
        return \App\Utils\Constants::upload('default_extension');
    }

    /**
     * 存储上传的文件
     */
    public function storeFile(UploadedFile $file, string $directory): array
    {
        try {
            // 验证文件
            $validation = $this->validateFile($file);
            if (! $validation['valid']) {
                return $this->error('File validation failed', $validation['errors']);
            }

            // 确保目录存在
            $this->ensureDirectoryExists($directory);

            // 生成文件信息
            $fileInfo = $this->generateFileInfo($file);

            // 移动文件到目标目录(优先使用 Storage，兼容测试环境)
            $originPath = $directory . '/' . $fileInfo['origin_filename'];
            $publicRoot = storage_path('app/public');
            $relativePath = $directory;
            if (str_starts_with($directory, $publicRoot)) {
                $relativePath = ltrim(substr($directory, strlen($publicRoot)), DIRECTORY_SEPARATOR);
            }

            $storedPath = Storage::disk('public')->putFileAs($relativePath, $file, $fileInfo['origin_filename']);
            if ($storedPath === false) {
                return $this->error('Failed to store file');
            }

            $this->logInfo('File stored successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $originPath,
                'size' => $file->getSize(),
            ]);

            return $this->success(array_merge($fileInfo, [
                'compressed_path' => $directory . '/' . $fileInfo['compressed_filename'],
                'origin_path' => $originPath,
            ]));

        } catch (\Throwable $e) {
            return $this->handleException($e, 'store file');
        }
    }

    /**
     * 创建用户目录
     */
    public function createUserDirectory(int $userId): array
    {
        try {
            $dirPath = storage_path("app/public/uploads/{$userId}");

            if (! $this->ensureDirectoryExists($dirPath)) {
                return $this->error('Failed to create user directory');
            }

            return $this->success(['directory_path' => $dirPath]);

        } catch (\Throwable $e) {
            return $this->handleException($e, 'create user directory');
        }
    }

    /**
     * 获取公共访问 URL
     */
    public function getPublicUrls(string $userId, array $filenames): array
    {
        $baseUrl = "uploads/{$userId}/";

        return [
            'compressed_url' => url("storage/{$baseUrl}{$filenames['compressed_filename']}"),
            'thumbnail_url' => url("storage/{$baseUrl}{$filenames['thumbnail_filename']}"),
            'origin_url' => url("storage/{$baseUrl}{$filenames['origin_filename']}"),
        ];
    }

    /**
     * 删除文件
     */
    public function deleteFile(string $filePath): array
    {
        try {
            if (! file_exists($filePath)) {
                return $this->error('File not found');
            }

            if (! unlink($filePath)) {
                return $this->error('Failed to delete file');
            }

            $this->logInfo('File deleted successfully', ['file_path' => $filePath]);

            return $this->success();

        } catch (\Throwable $e) {
            return $this->handleException($e, 'delete file');
        }
    }

    /**
     * 删除用户的所有相关文件
     */
    public function deleteUserFiles(string $userId, string $basename): array
    {
        try {
            $directory = storage_path("app/public/uploads/{$userId}");
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $deleted = 0;

            foreach ($extensions as $ext) {
                $files = [
                    "{$directory}/{$basename}.{$ext}",
                    "{$directory}/{$basename}-thumb.{$ext}",
                    "{$directory}/{$basename}-origin.{$ext}",
                ];

                foreach ($files as $file) {
                    if (file_exists($file) && unlink($file)) {
                        $deleted++;
                    }
                }
            }

            return $this->success(['deleted_files' => $deleted]);

        } catch (\Throwable $e) {
            return $this->handleException($e, 'delete user files');
        }
    }

    /**
     * 验证上传的文件
     */
    private function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // 检查文件大小
        $maxSize = $this->getMaxFileSize();
        if ($file->getSize() > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . ($maxSize / 1024 / 1024) . 'MB';
        }

        // 检查文件扩展名(允许无扩展名，使用默认扩展名)
        $originalExtension = strtolower($file->getClientOriginalExtension());
        $extension = $originalExtension;
        $allowedExtensions = $this->getAllowedExtensions();
        if ($extension === '') {
            $extension = $this->getDefaultExtension();
        } elseif (! in_array($extension, $allowedExtensions)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions);
        }

        // 仅在有明确扩展名且允许的图片扩展名时验证图片内容
        if ($originalExtension !== '' && in_array($extension, $allowedExtensions) && ! $this->isValidImage($file)) {
            $errors[] = 'File is not a valid image';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * 检查是否为有效图片
     */
    private function isValidImage(UploadedFile $file): bool
    {
        $imageInfo = @getimagesize($file->getPathname());

        return $imageInfo !== false;
    }

    /**
     * 生成文件信息
     */
    private function generateFileInfo(UploadedFile $file): array
    {
        $basename = Str::random(32);
        $extension = strtolower($file->getClientOriginalExtension()) ?: $this->getDefaultExtension();

        return [
            'basename' => $basename,
            'extension' => $extension,
            'compressed_filename' => "{$basename}.{$extension}",
            'thumbnail_filename' => "{$basename}-thumb.{$extension}",
            'origin_filename' => "{$basename}-origin.{$extension}",
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * 确保目录存在
     */
    private function ensureDirectoryExists(string $directory): bool
    {
        if (! file_exists($directory)) {
            return mkdir($directory, 0755, true);
        }

        return true;
    }
}
