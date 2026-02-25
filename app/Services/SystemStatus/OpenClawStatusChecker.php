<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenClaw 服务器健康检查。
 *
 * 契约：GET {OPENCLAW_HEALTH_URL} 返回 JSON，支持以下字段（均为可选）：
 * - online: bool  是否在线
 * - cpu_percent: float 0-100  CPU 使用率
 * - memory_percent: float 0-100  内存使用率
 * - disk_percent: float 0-100  磁盘使用率
 * 或 used/total 形式由本类换算为百分比：
 * - cpu: { used, total }
 * - memory: { used, total }
 * - disk: { used, total }
 * - message: string  附加说明
 *
 * 若未配置 OPENCLAW_HEALTH_URL，则直接返回 offline，不发起请求。
 */
class OpenClawStatusChecker
{
    private const WARNING_THRESHOLD = 90;

    private const ERROR_THRESHOLD = 98;

    public function check(): array
    {
        $url = config('services.openclaw.health_url');
        $timeout = config('services.openclaw.timeout_seconds', 5);

        if (empty($url)) {
            return $this->buildResult(false, 'offline', null, null, null, '未配置 OpenClaw 健康检查地址');
        }

        try {
            $response = Http::timeout($timeout)->get($url);

            if (! $response->successful()) {
                return $this->buildResult(false, 'error', null, null, null, 'OpenClaw 返回异常: HTTP ' . $response->status());
            }

            $body = $response->json();
            if (! is_array($body)) {
                return $this->buildResult(true, 'online', null, null, null, '响应格式无效，仅判定为在线');
            }

            $online = isset($body['online']) ? (bool) $body['online'] : true;
            $cpu = $this->normalizePercent($body, 'cpu');
            $memory = $this->normalizePercent($body, 'memory');
            $disk = $this->normalizePercent($body, 'disk');
            $message = isset($body['message']) && is_string($body['message']) ? $this->sanitize($body['message'], 200) : null;

            $status = $this->resolveStatus($online, $cpu, $memory, $disk);
            $details = $this->formatDetails($cpu, $memory, $disk, $message);

            return $this->buildResult($online, $status, $cpu, $memory, $disk, $details);
        } catch (\Throwable $e) {
            Log::warning('OpenClaw health check failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->buildResult(false, 'error', null, null, null, '无法连接 OpenClaw: ' . $this->sanitize($e->getMessage(), 100));
        }
    }

    private function normalizePercent(array $body, string $key): ?float
    {
        $percentKey = $key . '_percent';
        if (isset($body[$percentKey]) && is_numeric($body[$percentKey])) {
            $v = (float) $body[$percentKey];

            return max(0, min(100, $v));
        }
        $obj = $body[$key] ?? null;
        if (is_array($obj) && isset($obj['used'], $obj['total'])) {
            $used = (float) $obj['used'];
            $total = (float) $obj['total'];
            if ($total > 0) {
                return max(0, min(100, round(($used / $total) * 100, 1)));
            }
        }

        return null;
    }

    private function resolveStatus(bool $online, ?float $cpu, ?float $memory, ?float $disk): string
    {
        if (! $online) {
            return 'error';
        }
        $values = array_filter([$cpu, $memory, $disk], fn ($v) => $v !== null);
        foreach ($values as $v) {
            if ($v >= self::ERROR_THRESHOLD) {
                return 'error';
            }
        }
        foreach ($values as $v) {
            if ($v >= self::WARNING_THRESHOLD) {
                return 'warning';
            }
        }

        return 'online';
    }

    private function formatDetails(?float $cpu, ?float $memory, ?float $disk, ?string $message): string
    {
        $parts = [];
        if ($cpu !== null) {
            $parts[] = 'CPU: ' . $cpu . '%';
        }
        if ($memory !== null) {
            $parts[] = '内存: ' . $memory . '%';
        }
        if ($disk !== null) {
            $parts[] = '磁盘: ' . $disk . '%';
        }
        $line = implode(' | ', $parts);
        if ($message !== null && $message !== '') {
            $line = $line ? $line . ' | ' . $message : $message;
        }

        return $line ?: '无指标';
    }

    private function buildResult(
        bool $online,
        string $status,
        ?float $cpu,
        ?float $memory,
        ?float $disk,
        string $details
    ): array {
        return [
            'online' => $online,
            'status' => $status,
            'cpu_percent' => $cpu,
            'memory_percent' => $memory,
            'disk_percent' => $disk,
            'details' => $details,
        ];
    }

    private function sanitize(string $s, int $maxLen): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return strlen($s) > $maxLen ? substr($s, 0, $maxLen) . '…' : $s;
    }
}
