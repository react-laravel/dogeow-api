<?php

namespace App\Utils;

class Constants
{
    /**
     * 获取聊天配置
     */
    public static function chat(?string $section = null, ?string $key = null): mixed
    {
        $config = config('chat');

        if ($section && $key) {
            return $config[$section][$key] ?? null;
        }

        if ($section) {
            return $config[$section] ?? null;
        }

        return $config;
    }

    /**
     * 获取文件上传配置
     */
    public static function upload(?string $key = null): mixed
    {
        $config = config('app.upload');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取图片处理配置
     */
    public static function image(?string $key = null): mixed
    {
        $config = config('app.image');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取缓存配置
     */
    public static function cache(?string $key = null): mixed
    {
        $config = config('app.cache');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取验证配置
     */
    public static function validation(?string $section = null, ?string $key = null): mixed
    {
        $config = config('app.validation');

        if ($section && $key) {
            return $config[$section][$key] ?? null;
        }

        if ($section) {
            return $config[$section] ?? null;
        }

        return $config;
    }

    /**
     * 获取 API 配置
     */
    public static function api(?string $key = null): mixed
    {
        $config = config('app.api');

        return $key ? ($config[$key] ?? null) : $config;
    }

    // 快捷方法
    public static function chatMessageMaxLength(): int
    {
        return (int) self::chat('message', 'max_length');
    }

    public static function chatRoomNameMaxLength(): int
    {
        return (int) self::chat('room', 'name_max_length');
    }

    public static function maxFileSize(): int
    {
        return (int) self::upload('max_file_size');
    }

    /**
     * @return array<string>
     */
    public static function allowedExtensions(): array
    {
        return (array) self::upload('allowed_extensions');
    }

    public static function thumbnailSize(): int
    {
        return (int) self::image('thumbnail_size');
    }

    public static function compressedMaxSize(): int
    {
        return (int) self::image('compressed_max_size');
    }
}
