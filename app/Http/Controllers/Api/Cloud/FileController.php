<?php

namespace App\Http\Controllers\Api\Cloud;

use App\Http\Controllers\Concerns\GetCurrentUserId;
use App\Http\Controllers\Controller;
use App\Models\Cloud\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    use GetCurrentUserId;

    /**
     * 获取所有文件列表
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        $query = File::query()->where('user_id', $userId);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            $query->whereNull('parent_id');
        }

        // 搜索
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // 类型过滤
        if ($request->has('type')) {
            $query->whereHasFileType($request->type);
        }

        // 排序
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $files = $query->get();

        return response()->json($files);
    }

    /**
     * 创建文件夹
     */
    public function createFolder(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:cloud_files,id',
            'description' => 'nullable|string',
        ]);

        $userId = $this->getCurrentUserId();

        $file = new File;
        $file->name = $request->name;
        $file->parent_id = $request->parent_id;
        $file->user_id = $userId;
        $file->is_folder = true;
        $file->path = 'folders/' . Str::uuid();
        $file->description = $request->description;
        $file->save();

        return response()->json($file, 201);
    }

    /**
     * 上传文件
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
            'parent_id' => 'nullable|exists:cloud_files,id',
            'description' => 'nullable|string',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();
        $mimeType = $uploadedFile->getMimeType();
        $size = $uploadedFile->getSize();

        Log::info("上传文件: 原始名称={$originalName}, 扩展名={$extension}, MIME 类型={$mimeType}, 大小={$size}");

        $userId = $this->getCurrentUserId();

        // 生成唯一文件路径
        $fileName = Str::uuid() . '.' . $extension;
        $path = 'cloud/' . $userId . '/' . date('Y/m/d') . '/' . $fileName;

        Log::info("存储路径: {$path}");

        // 保存文件 - 使用 stream 方式避免大文件内存问题
        Storage::disk('public')->writeStream($path, fopen($uploadedFile->getRealPath(), 'r'));

        // 检查文件是否已保存
        if (! Storage::disk('public')->exists($path)) {
            Log::error("文件保存失败: {$path}");

            return response()->json(['error' => '文件保存失败'], 500);
        }

        Log::info("文件已保存到: {$path}");

        // 创建数据库记录
        $file = new File;
        $file->name = pathinfo($originalName, PATHINFO_FILENAME);
        $file->original_name = $originalName;
        $file->extension = $extension;
        $file->mime_type = $mimeType;
        $file->path = $path;
        $file->size = $size;
        $file->parent_id = $request->parent_id;
        $file->user_id = $userId;
        $file->description = $request->description;

        $file->save();

        Log::info("文件记录已创建: ID={$file->id}, 类型={$file->type}");

        return response()->json($file, 201);
    }

    /**
     * 下载文件
     */
    public function download($id): \Symfony\Component\HttpFoundation\Response
    {
        $userId = $this->getCurrentUserId();

        $file = File::where('user_id', $userId)->findOrFail($id);

        if ($file->is_folder) {
            return response()->json(['error' => '不能下载文件夹'], 400);
        }

        if (! Storage::disk('public')->exists($file->path)) {
            return response()->json(['error' => '文件不存在'], 404);
        }

        return response()->download(storage_path('app/public/' . $file->path), $file->original_name);
    }

    /**
     * 删除文件或文件夹
     */
    public function destroy($id): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        $file = File::where('user_id', $userId)->findOrFail($id);

        // 如果是文件夹，使用迭代方式递归删除
        if ($file->is_folder) {
            $this->deleteFolderIteratively($file);
        } else {
            // 删除存储的文件
            if (Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
            }

            // 删除数据库记录
            $file->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * 迭代方式删除文件夹(避免递归栈溢出)
     */
    private function deleteFolderIteratively(File $folder): void
    {
        $userId = $this->getCurrentUserId();
        $folderIds = [$folder->id];

        // 使用栈迭代处理所有文件夹
        while (! empty($folderIds)) {
            $currentFolderId = array_pop($folderIds);

            // 获取当前文件夹的所有子项
            $children = File::where('parent_id', $currentFolderId)
                ->where('user_id', $userId)
                ->get();

            foreach ($children as $child) {
                if ($child->is_folder) {
                    // 将子文件夹加入待处理队列
                    $folderIds[] = $child->id;
                } else {
                    // 删除子文件
                    if (Storage::disk('public')->exists($child->path)) {
                        Storage::disk('public')->delete($child->path);
                    }
                    $child->delete();
                }
            }

            // 删除当前文件夹
            File::where('id', $currentFolderId)->where('user_id', $userId)->delete();
        }
    }

    /**
     * 获取文件详情
     */
    public function show($id): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        $file = File::where('user_id', $userId)->findOrFail($id);

        return response()->json($file);
    }

    /**
     * 更新文件信息
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $userId = $this->getCurrentUserId();

        $file = File::where('user_id', $userId)->findOrFail($id);
        $file->name = $request->name;
        $file->description = $request->description;
        $file->save();

        return response()->json($file);
    }

    /**
     * 移动文件
     */
    public function move(Request $request): JsonResponse
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:cloud_files,id',
            'target_folder_id' => 'nullable|exists:cloud_files,id',
        ]);

        $userId = $this->getCurrentUserId();

        $targetFolderId = $request->target_folder_id;

        // 如果目标文件夹 ID 存在，验证它是否是文件夹
        if ($targetFolderId) {
            $targetFolder = File::where('user_id', $userId)
                ->where('id', $targetFolderId)
                ->where('is_folder', true)
                ->firstOrFail();

            // 确保不会将文件夹移动到自身或自身的子文件夹中
            $movingFolderIds = File::where('user_id', $userId)
                ->whereIn('id', $request->file_ids)
                ->where('is_folder', true)
                ->pluck('id');

            foreach ($movingFolderIds as $movingFolderId) {
                if ((int) $movingFolderId === (int) $targetFolderId) {
                    return response()->json(['error' => '不能将文件夹移动到自身或其子文件夹中'], 400);
                }

                $descendantIds = $this->getAllDescendantIds((int) $movingFolderId);
                if (in_array((int) $targetFolderId, $descendantIds, true)) {
                    return response()->json(['error' => '不能将文件夹移动到自身或其子文件夹中'], 400);
                }
            }
        }

        // 更新所有选中文件的父文件夹 ID
        File::where('user_id', $userId)
            ->whereIn('id', $request->file_ids)
            ->update(['parent_id' => $targetFolderId]);

        return response()->json(['success' => true]);
    }

    /**
     * 获取指定文件夹的所有后代 ID
     */
    private function getAllDescendantIds(int $folderId): array
    {
        $userId = $this->getCurrentUserId();
        $descendantIds = [];
        $queue = [$folderId];

        while (! empty($queue)) {
            $currentId = array_shift($queue);
            $children = File::where('parent_id', $currentId)
                ->where('user_id', $userId)
                ->where('is_folder', true)
                ->pluck('id')
                ->toArray();

            $descendantIds = array_merge($descendantIds, $children);
            $queue = array_merge($queue, $children);
        }

        return $descendantIds;
    }

    /**
     * 预览文件
     */
    public function preview($id, Request $request): JsonResponse
    {
        // 处理预检请求
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['message' => 'OK'])
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        $userId = $this->getCurrentUserId();

        $file = File::where('user_id', $userId)->findOrFail($id);

        if ($file->is_folder) {
            return response()->json(['error' => '不能预览文件夹'], 400);
        }

        if (! Storage::disk('public')->exists($file->path)) {
            Log::error("文件不存在: {$file->path}");

            return response()->json(['error' => '文件不存在'], 404);
        }

        $extension = strtolower($file->extension);
        $mimeType = $file->mime_type;

        // 图片文件
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'])) {
            $isThumb = $request->has('thumb') && $request->thumb === 'true';
            Log::info("图片预览请求: ID={$id}, 是否缩略图={$isThumb}, 扩展名={$extension}");

            $publicUrl = url('storage/' . $file->path);
            Log::info("返回图片 URL: {$publicUrl}");

            return response()->json([
                'type' => 'image',
                'url' => $publicUrl,
            ]);
        }

        // PDF 文件
        if ($extension === 'pdf') {
            return response()->json([
                'type' => 'pdf',
                'url' => url('storage/' . $file->path),
            ]);
        }

        // Apple 文档格式
        if (in_array($extension, ['pages', 'key', 'numbers'])) {
            return response()->json([
                'type' => 'document',
                'message' => '此文件是苹果 ' . strtoupper($extension) . ' 格式，需要在 Mac 上使用相应的应用程序打开',
                'suggestion' => '您可以下载文件后在 Mac 上打开，或者将其导出为 PDF 格式以便在线预览',
            ]);
        }

        // Microsoft Office 文档
        if (in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
            return response()->json([
                'type' => 'document',
                'message' => '此文件是 Microsoft Office 格式，需要使用相应的应用程序打开',
                'suggestion' => '您可以下载文件后使用 Microsoft Office 或其他兼容软件打开',
            ]);
        }

        // 文本文件
        if (in_array($extension, ['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php']) ||
            Str::startsWith($mimeType, 'text/')) {
            return response()->json([
                'type' => 'text',
                'content' => Storage::disk('public')->get($file->path),
            ]);
        }

        // 无法预览的文件
        return response()->json([
            'type' => 'unknown',
            'message' => '此文件类型不支持预览，请下载后查看',
        ]);
    }
}
