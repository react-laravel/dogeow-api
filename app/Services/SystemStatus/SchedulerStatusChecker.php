<?php

namespace App\Services\SystemStatus;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SchedulerStatusChecker
{
    private const HEARTBEAT_KEY = 'scheduler:heartbeat';

    private const HEARTBEAT_THRESHOLD = 90; // 秒

    /**
     * 检查调度器状态(通过心跳检测)
     *
     * @return array{status: string, details: string, last_run?: string}
     */
    public function check(): array
    {
        try {
            $lastHeartbeat = Cache::get(self::HEARTBEAT_KEY);

            if (! $lastHeartbeat) {
                return [
                    'status' => 'warning',
                    'details' => '未检测到调度器心跳',
                ];
            }

            $lastRun = Carbon::parse($lastHeartbeat);
            $secondsAgo = $lastRun->diffInSeconds(now());

            if ($secondsAgo > self::HEARTBEAT_THRESHOLD) {
                return [
                    'status' => 'error',
                    'details' => "调度器已停止 {$secondsAgo} 秒",
                    'last_run' => $lastRun->diffForHumans(),
                ];
            }

            return [
                'status' => 'online',
                'details' => "上次运行: {$lastRun->diffForHumans()}",
                'last_run' => $lastRun->diffForHumans(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'details' => '调度器状态检查失败: ' . $e->getMessage(),
            ];
        }
    }
}
