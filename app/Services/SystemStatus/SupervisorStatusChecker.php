<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * 按 Supervisor program 名查询进程状态。
 *
 * 执行 supervisorctl status {program_name}，解析输出中的状态并映射为：
 * - RUNNING -> online
 * - STARTING -> warning
 * - STOPPED, EXITED, STOPPING -> offline
 * - FATAL, UNKNOWN 或无法执行 -> error
 */
class SupervisorStatusChecker
{
    /** @var array<string, string> 原始状态 -> 归一化状态 */
    private const STATE_MAP = [
        'RUNNING' => 'online',
        'STARTING' => 'warning',
        'STOPPED' => 'offline',
        'EXITED' => 'offline',
        'STOPPING' => 'offline',
        'FATAL' => 'error',
        'UNKNOWN' => 'error',
        'BACKOFF' => 'error',
    ];

    /**
     * 查询单个 program 的状态。
     *
     * @return array{status: string, raw_state: string, details: string}
     */
    public function getProgramStatus(string $programName): array
    {
        if (trim($programName) === '') {
            return [
                'status' => 'error',
                'raw_state' => 'UNKNOWN',
                'details' => '未配置进程名',
            ];
        }

        try {
            $process = new Process(['supervisorctl', 'status', $programName]);
            $process->setTimeout(5);
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if ($process->getExitCode() !== 0) {
                Log::debug('Supervisor status non-zero exit', [
                    'program' => $programName,
                    'exit_code' => $process->getExitCode(),
                    'output' => $output,
                    'error' => $errorOutput,
                ]);
                return [
                    'status' => 'error',
                    'raw_state' => 'UNKNOWN',
                    'details' => $this->sanitize($errorOutput ?: $output ?: 'supervisorctl 执行失败', 120),
                ];
            }

            // 格式: "program_name    STATE    pid 123, uptime ..."
            $parts = preg_split('/\s{2,}/', $output, 3);
            $rawState = isset($parts[1]) ? strtoupper(trim($parts[1])) : 'UNKNOWN';
            $info = isset($parts[2]) ? trim($parts[2]) : '';

            $status = self::STATE_MAP[$rawState] ?? 'error';
            $details = $this->sanitize($info, 150);
            if ($details === '') {
                $details = $rawState;
            }

            return [
                'status' => $status,
                'raw_state' => $rawState,
                'details' => $details,
            ];
        } catch (\Throwable $e) {
            Log::warning('Supervisor status check failed', [
                'program' => $programName,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 'error',
                'raw_state' => 'UNKNOWN',
                'details' => $this->sanitize($e->getMessage(), 100),
            ];
        }
    }

    private function sanitize(string $s, int $maxLen): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return strlen($s) > $maxLen ? substr($s, 0, $maxLen) . '…' : $s;
    }
}
