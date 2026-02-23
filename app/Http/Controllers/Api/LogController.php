<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    private string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs');
    }

    /**
     * 获取可用的日志文件列表
     */
    public function index(): JsonResponse
    {
        $files = File::files($this->logPath);

        $logs = collect($files)
            ->filter(fn ($file) => preg_match('/laravel-\d{4}-\d{2}-\d{2}\.log/', $file->getFilename()))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'date' => preg_replace('/laravel-(\d{4}-\d{2}-\d{2})\.log/', '$1', $file->getFilename()),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ])
            ->sortByDesc('date')
            ->values();

        return response()->json($logs);
    }

    /**
     * 获取指定日期的日志内容
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}$/',
        ]);

        $date = $request->input('date');
        $filename = "laravel-{$date}.log";
        $filepath = $this->logPath . '/' . $filename;

        if (! File::exists($filepath)) {
            return response()->json(['message' => '日志文件不存在'], 404);
        }

        $content = File::get($filepath);

        // 按行分割并返回最后 N 行（默认 500 行）
        $lines = explode("\n", $content);
        $maxLines = (int) $request->input('lines', 500);
        $lines = array_slice($lines, -$maxLines);

        return response()->json([
            'date' => $date,
            'filename' => $filename,
            'content' => implode("\n", $lines),
            'total_lines' => count(explode("\n", $content)),
        ]);
    }
}
