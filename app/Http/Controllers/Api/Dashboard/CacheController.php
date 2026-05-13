<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\DashboardCacheRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CacheController extends Controller
{
    /**
     * 获取 dashboard 可管理的缓存项列表
     */
    public function index(): JsonResponse
    {
        $items = collect(DashboardCacheRegistry::all())
            ->map(function (array $item): array {
                $ttlSeconds = (int) $item['ttl_seconds'];

                return [
                    ...$item,
                    'ttl_human' => $this->formatTtl($ttlSeconds),
                    'has_value' => Cache::has($item['cache_key']),
                ];
            })
            ->values()
            ->all();

        return response()->json($items);
    }

    /**
     * 清理指定缓存项
     */
    public function destroy(string $id): JsonResponse
    {
        $item = DashboardCacheRegistry::find($id);

        if (! $item) {
            return response()->json(['message' => '缓存项不存在'], 404);
        }

        $cacheKey = $item['cache_key'];
        $hadValue = Cache::has($cacheKey);
        $forgotten = Cache::forget($cacheKey);

        return response()->json([
            'message' => '缓存已清理',
            'id' => $id,
            'cache_key' => $cacheKey,
            'had_value' => $hadValue,
            'forgotten' => $forgotten,
        ]);
    }

    private function formatTtl(int $ttlSeconds): string
    {
        if ($ttlSeconds % 3600 === 0) {
            return (string) ($ttlSeconds / 3600) . ' 小时';
        }

        if ($ttlSeconds % 60 === 0) {
            return (string) ($ttlSeconds / 60) . ' 分钟';
        }

        return (string) $ttlSeconds . ' 秒';
    }
}
