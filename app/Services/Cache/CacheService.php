<?php

namespace App\Services\Cache;

use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheService extends BaseService
{
    private const DEFAULT_TTL = 3600; // 1 hour

    private const SUCCESS_TTL = 86400; // 24 hours

    private const ERROR_TTL = 1800; // 30 minutes

    /**
     * 获取缓存数据
     */
    public function get(string $key, string $prefix = ''): mixed
    {
        return Cache::get($this->buildCacheKey($key, $prefix));
    }

    /**
     * 设置缓存数据
     */
    public function put(string $key, mixed $data, int $ttl = self::DEFAULT_TTL, string $prefix = ''): void
    {
        Cache::put($this->buildCacheKey($key, $prefix), $data, now()->addSeconds($ttl));
    }

    /**
     * 设置成功缓存（长期）
     */
    public function putSuccess(string $key, mixed $data, string $prefix = ''): void
    {
        $this->put($key, $data, self::SUCCESS_TTL, $prefix);
    }

    /**
     * 设置错误缓存（短期）
     */
    public function putError(string $key, mixed $data, string $prefix = ''): void
    {
        $this->put($key, $data, self::ERROR_TTL, $prefix);
    }

    /**
     * 删除缓存
     */
    public function forget(string $key, string $prefix = ''): void
    {
        Cache::forget($this->buildCacheKey($key, $prefix));
    }

    /**
     * 批量删除缓存（通过前缀）
     */
    public function forgetByPrefix(string $prefix): void
    {
        $pattern = $this->buildCacheKey('*', $prefix);
        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection();
        $keys = $redis->keys($pattern);

        if (! empty($keys)) {
            $redis->del($keys);
        }
    }

    /**
     * 记住缓存（如果不存在则执行回调并缓存结果）
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL, string $prefix = ''): mixed
    {
        return Cache::remember(
            $this->buildCacheKey($key, $prefix),
            now()->addSeconds($ttl),
            $callback
        );
    }

    /**
     * 构建缓存键
     */
    private function buildCacheKey(string $key, string $prefix = ''): string
    {
        $prefix = $prefix ?: 'app';

        return "{$prefix}:" . md5($key);
    }

    /**
     * 获取标题和图标缓存（保持向后兼容）
     */
    public function getTitleFavicon(string $url): ?array
    {
        return $this->get($url, 'title_favicon');
    }

    /**
     * 设置标题和图标成功缓存
     */
    public function putTitleFaviconSuccess(string $url, array $data): void
    {
        $this->putSuccess($url, $data, 'title_favicon');
    }

    /**
     * 设置标题和图标错误缓存
     */
    public function putTitleFaviconError(string $url, array $errorData): void
    {
        $this->putError($url, $errorData, 'title_favicon');
    }
}
