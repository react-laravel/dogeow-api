<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * 按 program 名进行进程探针状态检查。
 *
 * 通过 `pgrep -f` 检查进程是否存在：
 * - 命中(exit code 0)-> online / RUNNING
 * - 未命中(exit code 1)-> offline / STOPPED
 * - 其他错误 -> error / UNKNOWN
 */
class SupervisorStatusChecker
{
    private const NO_MATCH_EXIT_CODE = 1;

    private const PROCESS_PROBE_TIMEOUT_SECONDS = 3;

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

        $pattern = $this->resolveProbePattern($programName);

        try {
            $probeResult = $this->executeProcessProbe($pattern);
        } catch (\Throwable $e) {
            Log::warning('Process probe failed', [
                'program' => $programName,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            // 公开端点不回显底层异常细节（已在上方写入日志）
            return [
                'status' => 'error',
                'raw_state' => 'UNKNOWN',
                'details' => '进程探针执行失败',
            ];
        }

        if ($probeResult['exitCode'] === 0) {
            // 不在公开响应中暴露 PID 等进程细节
            return [
                'status' => 'online',
                'raw_state' => 'RUNNING',
                'details' => '进程探针命中，进程运行中',
            ];
        }

        if ($probeResult['exitCode'] === self::NO_MATCH_EXIT_CODE) {
            return [
                'status' => 'offline',
                'raw_state' => 'STOPPED',
                'details' => '进程探针未命中，未发现运行中的进程',
            ];
        }

        Log::warning('Process probe returned unexpected exit code', [
            'program' => $programName,
            'pattern' => $pattern,
            'exit_code' => $probeResult['exitCode'],
            'output' => $probeResult['output'],
            'error' => $probeResult['error'],
        ]);

        // 进程探针的原始 stderr/stdout 可能包含内部路径/命令细节，
        // 已在上方写入日志，公开响应仅返回通用错误文案
        return [
            'status' => 'error',
            'raw_state' => 'UNKNOWN',
            'details' => '进程探针执行失败',
        ];
    }

    /**
     * 执行 fallback 进程探针命令(pgrep -f)。
     * 可被重写以用于测试。
     *
     * @return array{output: string, error: string, exitCode: int}
     */
    protected function executeProcessProbe(string $pattern): array
    {
        $process = new Process(['pgrep', '-f', $pattern]);
        $process->setTimeout(self::PROCESS_PROBE_TIMEOUT_SECONDS);
        $process->run();

        return [
            'output' => trim($process->getOutput()),
            'error' => trim($process->getErrorOutput()),
            'exitCode' => $process->getExitCode(),
        ];
    }

    private function resolveProbePattern(string $programName): string
    {
        $reverbProgram = (string) config('services.supervisor.reverb_program', 'reverb');
        if ($programName === $reverbProgram) {
            return trim((string) config('services.supervisor.reverb_probe_pattern', 'artisan reverb:start'));
        }

        $queueProgram = (string) config('services.supervisor.queue_program', 'queue-default');
        if ($programName === $queueProgram) {
            return trim((string) config('services.supervisor.queue_probe_pattern', 'artisan queue:(work|listen)'));
        }

        return trim($programName);
    }
}
