<?php

namespace App\Support;

class DashboardCacheRegistry
{
    public const MUSIC_UPYUN_LIST = 'musics_upyun_list';

    /**
     * @return array<string, array{id: string, name: string, description: string, cache_key: string, ttl_seconds: int}>
     */
    public static function definitions(): array
    {
        return [
            self::MUSIC_UPYUN_LIST => [
                'id' => self::MUSIC_UPYUN_LIST,
                'name' => '音乐列表缓存',
                'description' => '用于 /api/musics，避免每次请求都访问又拍云目录接口',
                'cache_key' => 'musics:upyun:/music:v2',
                'ttl_seconds' => 21600,
            ],
        ];
    }

    /**
     * @return array{id: string, name: string, description: string, cache_key: string, ttl_seconds: int}|null
     */
    public static function find(string $id): ?array
    {
        return self::definitions()[$id] ?? null;
    }

    /**
     * @return array<array{id: string, name: string, description: string, cache_key: string, ttl_seconds: int}>
     */
    public static function all(): array
    {
        return array_values(self::definitions());
    }
}
