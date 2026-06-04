<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * 本机 Hermes（OpenClaw Gateway）健康检查。
 *
 * 探测顺序：
 * 1. HTTP GET（默认 http://127.0.0.1:18789/health，失败时尝试根路径）
 * 2. 进程探针 pgrep -f（默认识别 openclaw gateway）
 * 3. CLI：openclaw health --json（若可执行）
 *
 * HTTP/CLI JSON 支持字段：
 * - ok: bool（OpenClaw health 快照）
 * - online: bool
 * - cpu_percent / memory_percent / disk_percent，或 cpu|memory|disk 的 used/total
 * - message: string
 *
 * 同机部署且响应无资源指标时，可选用本机磁盘/内存估算（HERMES_USE_HOST_METRICS）。
 */
class HermesStatusChecker
{
    private const WARNING_THRESHOLD = 90;

    private const ERROR_THRESHOLD = 98;

    private const PROCESS_PROBE_TIMEOUT_SECONDS = 3;

    public function check(): array
    {
        if (! config('services.hermes.enabled', true)) {
            return $this->buildResult(false, 'offline', null, null, null, 'Hermes 健康检查已禁用');
        }

        $httpResult = $this->checkViaHttp();
        if ($httpResult !== null) {
            return $httpResult;
        }

        $processResult = $this->checkViaProcessProbe();
        if ($processResult !== null) {
            return $this->mergeHostMetricsWhenMissing($processResult);
        }

        $cliResult = $this->checkViaCli();
        if ($cliResult !== null) {
            return $this->mergeHostMetricsWhenMissing($cliResult);
        }

        return $this->buildResult(
            false,
            'error',
            null,
            null,
            null,
            sprintf(
                '无法连接 Hermes（已尝试 HTTP、进程探针与 CLI，目标 %s:%d）',
                config('services.hermes.host', '127.0.0.1'),
                (int) config('services.hermes.port', 18789)
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkViaHttp(): ?array
    {
        $timeout = (int) config('services.hermes.timeout_seconds', 5);
        $maxRedirects = max(0, (int) config('services.hermes.max_redirects', 5));

        foreach ($this->resolveHealthUrls() as $url) {
            try {
                $startedAt = microtime(true);
                $response = Http::timeout($timeout)
                    ->withOptions([
                        'allow_redirects' => [
                            'max' => $maxRedirects,
                        ],
                    ])
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $responseTimeMs = round((microtime(true) - $startedAt) * 1000, 2);
                $body = $response->json();
                if (! is_array($body)) {
                    return $this->buildResult(
                        true,
                        'online',
                        null,
                        null,
                        null,
                        sprintf('Hermes 可访问（%s）', $this->sanitize($url, 80)),
                        $responseTimeMs
                    );
                }

                return $this->parseHealthPayload(
                    $body,
                    sprintf('HTTP %s', $this->sanitize($url, 60)),
                    $responseTimeMs
                );
            } catch (\Throwable $e) {
                Log::debug('Hermes HTTP probe failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkViaProcessProbe(): ?array
    {
        $pattern = trim((string) config('services.hermes.probe_pattern', 'openclaw gateway'));
        if ($pattern === '') {
            return null;
        }

        try {
            $startedAt = microtime(true);
            $process = new Process(['pgrep', '-f', $pattern]);
            $process->setTimeout(self::PROCESS_PROBE_TIMEOUT_SECONDS);
            $process->run();

            if ($process->getExitCode() === 0) {
                $responseTimeMs = round((microtime(true) - $startedAt) * 1000, 2);
                $pid = $this->extractFirstPid(trim($process->getOutput()));
                $details = $pid !== null
                    ? sprintf('Hermes 进程运行中（PID %s）', $pid)
                    : 'Hermes 进程探针命中';

                return $this->buildResult(true, 'online', null, null, null, $details, $responseTimeMs);
            }

            if ($process->getExitCode() === 1) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::debug('Hermes process probe failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkViaCli(): ?array
    {
        $binary = trim((string) config('services.hermes.cli_binary', 'openclaw'));
        if ($binary === '') {
            return null;
        }

        $timeout = (int) config('services.hermes.timeout_seconds', 5);

        try {
            $startedAt = microtime(true);
            $process = Process::fromShellCommandline(
                escapeshellarg($binary) . ' health --json 2>/dev/null'
            );
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $responseTimeMs = round((microtime(true) - $startedAt) * 1000, 2);
            $body = json_decode(trim($process->getOutput()), true);
            if (! is_array($body)) {
                return $this->buildResult(true, 'online', null, null, null, 'Hermes CLI 健康检查通过', $responseTimeMs);
            }

            return $this->parseHealthPayload($body, 'openclaw health --json', $responseTimeMs);
        } catch (\Throwable $e) {
            Log::debug('Hermes CLI probe failed', [
                'binary' => $binary,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function parseHealthPayload(array $body, string $sourceLabel, ?float $responseTimeMs = null): array
    {
        $online = $this->resolveOnlineFlag($body);
        $cpu = $this->normalizePercent($body, 'cpu');
        $memory = $this->normalizePercent($body, 'memory');
        $disk = $this->normalizePercent($body, 'disk');
        $message = isset($body['message']) && is_string($body['message'])
            ? $this->sanitize($body['message'], 200)
            : null;

        $result = $this->buildResult(
            $online,
            $this->resolveStatus($online, $cpu, $memory, $disk),
            $cpu,
            $memory,
            $disk,
            $this->formatDetails($cpu, $memory, $disk, $message, $sourceLabel),
            $responseTimeMs
        );

        return $this->mergeHostMetricsWhenMissing($result);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function resolveOnlineFlag(array $body): bool
    {
        if (array_key_exists('ok', $body)) {
            return (bool) $body['ok'];
        }

        if (isset($body['online'])) {
            return (bool) $body['online'];
        }

        if (isset($body['status']) && is_string($body['status'])) {
            return in_array(strtolower($body['status']), ['ok', 'running', 'healthy'], true);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mergeHostMetricsWhenMissing(array $result): array
    {
        if (! config('services.hermes.use_host_metrics', true)) {
            return $result;
        }

        $cpu = $result['cpu_percent'];
        $memory = $result['memory_percent'];
        $disk = $result['disk_percent'];

        if ($cpu !== null && $memory !== null && $disk !== null) {
            return $result;
        }

        $host = $this->collectHostMetrics();
        $cpu = $cpu ?? $host['cpu_percent'];
        $memory = $memory ?? $host['memory_percent'];
        $disk = $disk ?? $host['disk_percent'];

        $online = (bool) $result['online'];
        $metricLine = $this->formatMetricLine($cpu, $memory, $disk);
        $existingDetails = (string) $result['details'];
        $details = $metricLine !== ''
            ? ($existingDetails !== '' && $existingDetails !== '无指标'
                ? $metricLine . ' | ' . $existingDetails
                : $metricLine)
            : $existingDetails;

        return $this->buildResult(
            $online,
            $this->resolveStatus($online, $cpu, $memory, $disk),
            $cpu,
            $memory,
            $disk,
            $details,
            $result['response_time'] ?? null
        );
    }

    /**
     * @return array{cpu_percent: ?float, memory_percent: ?float, disk_percent: ?float}
     */
    private function collectHostMetrics(): array
    {
        $diskPercent = $this->readDiskUsePercent('/');
        $memoryPercent = $this->readMemoryUsePercent();

        return [
            'cpu_percent' => null,
            'memory_percent' => $memoryPercent,
            'disk_percent' => $diskPercent,
        ];
    }

    private function readDiskUsePercent(string $path): ?float
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        return max(0, min(100, round((1 - ($free / $total)) * 100, 1)));
    }

    private function readMemoryUsePercent(): ?float
    {
        if (! is_readable('/proc/meminfo')) {
            return null;
        }

        $contents = @file_get_contents('/proc/meminfo');
        if ($contents === false) {
            return null;
        }

        $memTotal = null;
        $memAvailable = null;

        foreach (preg_split('/\R+/', $contents) as $line) {
            if (! is_string($line) || $line === '') {
                continue;
            }

            if (preg_match('/^MemTotal:\s+(\d+)\s+kB$/i', $line, $matches) === 1) {
                $memTotal = (float) $matches[1];
            }

            if (preg_match('/^MemAvailable:\s+(\d+)\s+kB$/i', $line, $matches) === 1) {
                $memAvailable = (float) $matches[1];
            }
        }

        if ($memTotal === null || $memTotal <= 0 || $memAvailable === null) {
            return null;
        }

        $used = max(0, $memTotal - $memAvailable);

        return max(0, min(100, round(($used / $memTotal) * 100, 1)));
    }

    /**
     * @return list<string>
     */
    private function resolveHealthUrls(): array
    {
        $explicit = trim((string) config('services.hermes.health_url', ''));
        $host = trim((string) config('services.hermes.host', '127.0.0.1'));
        $port = (int) config('services.hermes.port', 18789);
        $path = (string) config('services.hermes.health_path', '/health');
        $path = $path === '' ? '/health' : (str_starts_with($path, '/') ? $path : '/' . $path);

        $base = sprintf('http://%s:%d', $host, $port);
        $candidates = [];

        if ($explicit !== '') {
            $candidates[] = $explicit;
        }

        $candidates[] = $base . $path;
        if ($path !== '/') {
            $candidates[] = $base . '/';
        }

        return array_values(array_unique($candidates));
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

    private function formatDetails(
        ?float $cpu,
        ?float $memory,
        ?float $disk,
        ?string $message,
        ?string $sourceLabel = null
    ): string {
        $line = $this->formatMetricLine($cpu, $memory, $disk);
        if ($message !== null && $message !== '') {
            $line = $line ? $line . ' | ' . $message : $message;
        }
        if ($sourceLabel !== null && $sourceLabel !== '') {
            $line = $line ? $line . ' | ' . $sourceLabel : $sourceLabel;
        }

        return $line ?: '无指标';
    }

    private function formatMetricLine(?float $cpu, ?float $memory, ?float $disk): string
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

        return implode(' | ', $parts);
    }

    /**
     * @return array{online: bool, status: string, cpu_percent: ?float, memory_percent: ?float, disk_percent: ?float, details: string, response_time?: float}
     */
    private function buildResult(
        bool $online,
        string $status,
        ?float $cpu,
        ?float $memory,
        ?float $disk,
        string $details,
        ?float $responseTimeMs = null
    ): array {
        $result = [
            'online' => $online,
            'status' => $status,
            'cpu_percent' => $cpu,
            'memory_percent' => $memory,
            'disk_percent' => $disk,
            'details' => $details,
        ];

        if ($responseTimeMs !== null) {
            $result['response_time'] = $responseTimeMs;
        }

        return $result;
    }

    private function extractFirstPid(string $output): ?string
    {
        $lines = preg_split('/\R+/', trim($output));
        if (! is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            if (! is_string($line) || $line === '') {
                continue;
            }

            if (preg_match('/^(\d+)$/', $line, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function sanitize(string $s, int $maxLen): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return strlen($s) > $maxLen ? substr($s, 0, $maxLen) . '…' : $s;
    }
}
