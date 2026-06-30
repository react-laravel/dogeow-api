<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisStatusChecker
{
    /**
     * 检查 Redis 连接状态
     *
     * @return array{status: string, details: string, response_time?: float}
     */
    public function check(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'online',
                'details' => "响应时间: {$responseTime}ms",
                'response_time' => $responseTime,
            ];
        } catch (Throwable $e) {
            // 该状态接口为公开端点，不能向匿名用户回显底层异常细节
            Log::warning('Redis 连接失败', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'details' => 'Redis 连接失败',
            ];
        }
    }
}
