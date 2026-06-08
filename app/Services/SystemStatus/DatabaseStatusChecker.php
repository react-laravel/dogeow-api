<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseStatusChecker
{
    /**
     * 检查数据库连接状态
     *
     * @return array{status: string, label: string, driver: string, details: string, response_time?: float}
     */
    public function check(): array
    {
        $driver = (string) config('database.default', 'mysql');
        $label = $this->driverLabel($driver);

        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'online',
                'label' => $label,
                'driver' => $driver,
                'details' => "响应时间: {$responseTime}ms",
                'response_time' => $responseTime,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'label' => $label,
                'driver' => $driver,
                'details' => '数据库连接失败: ' . $e->getMessage(),
            ];
        }
    }

    private function driverLabel(string $driver): string
    {
        return match ($driver) {
            'pgsql' => 'PostgreSQL 数据库',
            'mysql' => 'MySQL 数据库',
            'mariadb' => 'MariaDB 数据库',
            'sqlite' => 'SQLite 数据库',
            default => '数据库',
        };
    }
}
