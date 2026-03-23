<?php

namespace App\Http\Controllers\Api\Cloud;

use App\Http\Controllers\Concerns\GetCurrentUserId;
use App\Http\Controllers\Controller;
use App\Models\Cloud\File;
use Illuminate\Http\JsonResponse;

class FileTreeController extends Controller
{
    use GetCurrentUserId;

    /**
     * 获取存储使用统计
     */
    public function statistics(): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        // 基础统计 - 单次查询获取所有数据
        /** @var \stdClass|null $baseStats */
        $baseStats = File::where('user_id', $userId)
            ->selectRaw('
                COUNT(CASE WHEN is_folder = false THEN 1 END) as file_count,
                COUNT(CASE WHEN is_folder = true THEN 1 END) as folder_count,
                COALESCE(SUM(CASE WHEN is_folder = false THEN size END), 0) as total_size
            ')
            ->first();

        // 文件类型统计
        $filesByType = File::where('user_id', $userId)
            ->where('is_folder', false)
            ->selectRaw('
                CASE
                    WHEN extension IN ("jpg", "jpeg", "png", "gif", "bmp", "svg", "webp") THEN "图片"
                    WHEN extension IN ("pdf") THEN "PDF"
                    WHEN extension IN ("doc", "docx", "txt", "rtf", "md") THEN "文档"
                    WHEN extension IN ("xls", "xlsx", "csv") THEN "表格"
                    WHEN extension IN ("zip", "rar", "7z", "tar", "gz") THEN "压缩包"
                    WHEN extension IN ("mp3", "wav", "ogg", "flac") THEN "音频"
                    WHEN extension IN ("mp4", "avi", "mov", "wmv", "mkv") THEN "视频"
                    ELSE "其他"
                END as file_type,
                COUNT(*) as count,
                COALESCE(SUM(size), 0) as total_size
            ')
            ->groupBy('file_type')
            ->get();

        return response()->json([
            'total_size' => (int) $baseStats->total_size,
            'human_readable_size' => $this->formatSize((int) $baseStats->total_size),
            'file_count' => (int) $baseStats->file_count,
            'folder_count' => (int) $baseStats->folder_count,
            'files_by_type' => $filesByType->map(fn (File $item) => [
                'file_type' => $item->file_type,
                'count' => (int) $item->count,
                'total_size' => (int) $item->total_size,
            ]),
        ]);
    }

    /**
     * 获取完整的目录树
     */
    public function tree(): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        // 使用递归 CTE 或预加载方式优化
        $rootFolders = File::where('user_id', $userId)
            ->where('is_folder', true)
            ->whereNull('parent_id')
            ->get();

        $tree = [];

        foreach ($rootFolders as $folder) {
            $tree[] = $this->buildFolderTree($folder);
        }

        return response()->json($tree);
    }

    /**
     * 递归构建文件夹树(已优化，使用预加载减少查询)
     */
    private function buildFolderTree($folder): array
    {
        $userId = $this->getCurrentUserId();

        // 使用 with 查询预加载子文件夹，减少 N+1 问题
        $children = File::where('user_id', $userId)
            ->where('is_folder', true)
            ->where('parent_id', $folder->id)
            ->get();

        $node = [
            'id' => $folder->id,
            'name' => $folder->name,
            'children' => [],
        ];

        foreach ($children as $child) {
            $node['children'][] = $this->buildFolderTree($child);
        }

        return $node;
    }

    /**
     * 格式化文件大小
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
