<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UpyunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class MusicController extends Controller
{
    private const CACHE_KEY = 'musics:upyun:/music:v2';

    public function __construct(private readonly ?UpyunService $upyunService = null) {}

    /**
     * 获取所有可用的音乐列表
     */
    public function index(): JsonResponse
    {
        $upyunService = $this->upyunService ?? app(UpyunService::class);

        if (! $upyunService->isConfigured()) {
            return response()->json(['error' => '又拍云未配置'], 503);
        }

        $musicList = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () use ($upyunService) {
            $result = $upyunService->listDirectory('/music');

            if (! ($result['success'] ?? false)) {
                throw new \RuntimeException($result['message'] ?? '又拍云目录读取失败');
            }

            return collect($result['files'] ?? [])
                ->filter(function (array $file) {
                    return $this->isSupportedAudioExtension($file['name']);
                })
                ->map(function (array $file) use ($upyunService) {
                    $filename = (string) $file['name'];
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    return [
                        'name' => pathinfo($filename, PATHINFO_FILENAME),
                        'path' => $upyunService->buildPublicUrl('/music/' . rawurlencode($filename)),
                        'size' => (int) $file['length'],
                        'extension' => $extension,
                    ];
                })
                ->values()
                ->toArray();
        });

        return response()->json($musicList);
    }

    /**
     * 读取与音频同名的 LRC 歌词文件
     */
    public function lyrics(string $filename): HttpResponse|JsonResponse
    {
        $upyunService = $this->upyunService ?? app(UpyunService::class);

        if (! $upyunService->isConfigured()) {
            return response()->json(['error' => '又拍云未配置'], 503);
        }

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $lyricsPath = '/music/' . $basename . '.lrc';
        $result = $upyunService->readFile($lyricsPath);

        if (! ($result['success'] ?? false)) {
            $status = (int) ($result['status'] ?? 500);

            if ($status === 404) {
                return response()->json(['error' => '歌词不存在'], 404);
            }

            return response()->json(
                ['error' => $result['message'] ?? '歌词读取失败'],
                $status > 0 ? $status : 500
            );
        }

        return response($result['body'] ?? '', 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * 下载音乐文件，确保正确的 MIME 类型和响应头
     */
    public function download(string $filename): Response|\Illuminate\Http\JsonResponse
    {
        $filePath = public_path('musics/' . $filename);

        if (! File::exists($filePath)) {
            return response()->json(['error' => '文件不存在'], 404);
        }

        $mimeType = $this->getMimeType($filePath);
        $fileSize = File::size($filePath);

        // 设置正确的响应头
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ];

        // 支持范围请求(用于音频流式播放)
        $rangeHeader = request()->header('Range');
        if ($rangeHeader) {
            if (preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches)) {
                $start = $matches[1] === '' ? 0 : (int) $matches[1];
                $end = ($matches[2] !== '') ? (int) $matches[2] : ($fileSize - 1);

                // 保证范围合法
                $start = max(0, min($start, $fileSize - 1));
                $end = max($start, min($end, $fileSize - 1));
                $length = $end - $start + 1;

                $headers['Content-Range'] = "bytes $start-$end/$fileSize";
                $headers['Content-Length'] = $length;

                return response()->stream(
                    function () use ($filePath, $start, $length) {
                        $handle = fopen($filePath, 'rb');
                        fseek($handle, $start);
                        $bufferSize = 8192;
                        $remaining = $length;
                        while ($remaining > 0 && ! feof($handle)) {
                            $read = min($bufferSize, $remaining);
                            $buffer = fread($handle, $read);
                            echo $buffer;
                            $remaining -= $read;
                            flush();
                        }
                        fclose($handle);
                    },
                    206,
                    $headers
                );
            }
        }

        // 普通文件下载
        return response()->file($filePath, $headers);
    }

    /**
     * 获取文件的 MIME 类型，确保返回正确的音频类型
     */
    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $defaultType = 'application/octet-stream';

        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
        ];

        return $mimeTypes[$extension] ?? $defaultType;
    }

    private function isSupportedAudioExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['mp3', 'ogg', 'wav', 'flac', 'm4a', 'aac'], true);
    }
}
