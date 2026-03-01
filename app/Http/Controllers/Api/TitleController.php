<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cache\CacheService;
use App\Services\Web\WebPageService;
use Illuminate\Http\Request;

class TitleController extends Controller
{
    public function __construct(
        private readonly WebPageService $webPageService,
        private readonly CacheService $cacheService
    ) {}

    public function fetch(Request $request)
    {
        $url = $request->query('url');
        if (! $url) {
            return response()->json(['error' => '缺少url参数'], 400);
        }

        // 先检查缓存
        $cachedData = $this->cacheService->get($url);
        if ($cachedData !== null) {
            // 如果缓存存在错误数据，返回错误响应
            if (isset($cachedData['error'])) {
                $statusCode = $cachedData['status_code'] ?? 500;

                // return raw cached error structure to match tests
                return response()->json($cachedData, $statusCode);
            }

            // 如果缓存存在成功数据，直接返回原始缓存内容（测试期望无额外message）
            return response()->json($cachedData);
        }

        // 缓存不存在，获取新数据
        try {
            $data = $this->webPageService->fetchContent($url);
            $this->cacheService->putSuccess($url, $data);

            // 返回原始数据而不是封装
            return response()->json($data);
        } catch (\Exception $e) {
            $errorData = [
                'error' => '请求异常',
                'details' => $e->getMessage(),
                'status_code' => 500,
            ];
            $this->cacheService->putError($url, $errorData);

            return response()->json($errorData, 500);
        }
    }
}
