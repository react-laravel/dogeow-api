<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseStatusChecker
{
    /**
     * 检查数据库连接状态
     *
     * @return array{status: string, details: string, response_time?: float}
     */
    public function check(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'online',
                'details' => "响应时间: {$responseTime}ms",
                'response_time' => $responseTime,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'details' => '数据库连接失败: ' . $e->getMessage(),
            ];
        }
    }
}
