<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Services\UpyunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisionUploadController extends Controller
{
    private const HEIC_MIME_TYPES = [
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
    ];

    private const HEIC_EXTENSIONS = ['heic', 'heif'];

    public function __construct(
        private readonly UpyunService $upyunService
    ) {}

    /**
     * 上传图片到又拍云(用于 AI 视觉理解)，与 /api/upload/images 一致传二进制
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => [
                'required',
                'file',
                'max:20480',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! $this->isSupportedImageUpload($value)) {
                        $fail('上传文件必须是图片');
                    }
                },
            ],
        ], [
            'image.required' => '请选择要上传的图片',
            'image.file' => '上传文件必须是图片',
            'image.max' => '单张图片不能超过 20MB',
        ]);

        $file = $request->file('image');

        if (! $file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => '上传的图片无效',
            ], 400);
        }

        $mime = $this->getUploadMimeType($file);
        $extension = $this->getUploadExtension($file, $mime);

        $filename = sprintf('vision-%s.%s', Str::uuid(), $extension);
        $remotePath = '/vision/' . $filename;

        // 先存到带正确扩展名的临时文件再上传，避免 PHP 临时路径无扩展名导致又拍云/流读取异常
        $tempPath = Storage::disk('local')->path('vision-temp/' . $filename);
        $dir = dirname($tempPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);

        try {
            $result = $this->upyunService->upload($tempPath, $remotePath, $mime);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? '上传失败',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'url' => $result['url'],
        ]);
    }

    private function isSupportedImageUpload(mixed $file): bool
    {
        if (! $file || ! method_exists($file, 'getPathname')) {
            return false;
        }

        $extension = $this->uploadedFileValue($file, 'getClientOriginalExtension');
        $mime = $this->uploadedFileValue($file, 'getMimeType');
        $clientMime = $this->uploadedFileValue($file, 'getClientMimeType');

        if ($this->isHeicUpload($extension, $mime, $clientMime)) {
            return true;
        }

        return @getimagesize($file->getPathname()) !== false;
    }

    private function getUploadMimeType(mixed $file): string
    {
        $mime = $this->uploadedFileValue($file, 'getMimeType');
        $clientMime = $this->uploadedFileValue($file, 'getClientMimeType');
        $extension = $this->uploadedFileValue($file, 'getClientOriginalExtension');

        if ($this->isHeicUpload($extension, $mime, $clientMime)) {
            return in_array($clientMime, self::HEIC_MIME_TYPES, true)
                ? $clientMime
                : ($extension === 'heif' ? 'image/heif' : 'image/heic');
        }

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function getUploadExtension(mixed $file, string $mime): string
    {
        $extension = $this->uploadedFileValue($file, 'getClientOriginalExtension');
        $clientMime = $this->uploadedFileValue($file, 'getClientMimeType');

        if ($this->isHeicUpload($extension, $mime, $clientMime)) {
            return $extension === 'heif' || str_contains($mime, 'heif') || str_contains($clientMime, 'heif')
                ? 'heif'
                : 'heic';
        }

        return match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function uploadedFileValue(mixed $file, string $method): string
    {
        try {
            return strtolower((string) $file->{$method}());
        } catch (\Throwable) {
            return '';
        }
    }

    private function isHeicUpload(string $extension, string $mime, string $clientMime): bool
    {
        return in_array($extension, self::HEIC_EXTENSIONS, true)
            || in_array($mime, self::HEIC_MIME_TYPES, true)
            || in_array($clientMime, self::HEIC_MIME_TYPES, true);
    }
}
