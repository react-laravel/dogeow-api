<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UpyunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisionUploadController extends Controller
{
    public function __construct(
        private readonly UpyunService $upyunService
    ) {}

    /**
     * 上传图片到又拍云（用于AI视觉理解），与 /api/upload/images 一致传二进制
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:20480',
        ], [
            'image.required' => '请选择要上传的图片',
            'image.image' => '上传文件必须是图片',
            'image.max' => '单张图片不能超过 20MB',
        ]);

        $file = $request->file('image');

        if (! $file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => '上传的图片无效',
            ], 400);
        }

        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

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
}
