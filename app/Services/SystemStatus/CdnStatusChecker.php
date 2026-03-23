<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\Http;
use Throwable;

class CdnStatusChecker
{
    /**
     * 检查 CDN 服务状态(又拍云)
     *
     * @return array{status: string, details: string, response_time?: float}
     */
    public function check(): array
    {
        $cdnUrl = config('services.upyun.cdn_url');

        if (empty($cdnUrl)) {
            return [
                'status' => 'warning',
                'details' => 'CDN URL 未配置',
            ];
        }

        try {
            // 检查一个已知存在的资源(背景图片)
            $testUrl = rtrim($cdnUrl, '/') . '/bg/tesla-vector-roadster.png';
            $start = microtime(true);

            $response = Http::timeout(5)->head($testUrl); // 使用 HEAD 请求更轻量
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($response->successful()) {
                return [
                    'status' => 'online',
                    'details' => "响应时间: {$responseTime}ms",
                    'response_time' => $responseTime,
                ];
            }

            return [
                'status' => 'warning',
                'details' => "CDN 响应异常: HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'details' => 'CDN 连接失败: ' . $e->getMessage(),
            ];
        }
    }
}
