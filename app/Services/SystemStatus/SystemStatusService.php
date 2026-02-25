<?php

namespace App\Services\SystemStatus;

/**
 * 聚合 OpenClaw、Reverb、Queue 状态，返回前端所需的统一 DTO。
 */
class SystemStatusService
{
    public function __construct(
        private OpenClawStatusChecker $openclawChecker,
        private SupervisorStatusChecker $supervisorChecker
    ) {}

    /**
     * @return array{
     *   openclaw: array{online: bool, status: string, details: string, cpu_percent?: float, memory_percent?: float, disk_percent?: float},
     *   reverb: array{status: string, raw_state: string, details: string},
     *   queue: array{status: string, raw_state: string, details: string}
     * }
     */
    public function getAggregatedStatus(): array
    {
        $openclaw = $this->openclawChecker->check();
        $reverbProgram = config('services.supervisor.reverb_program', 'reverb');
        $queueProgram = config('services.supervisor.queue_program', 'queue-default');

        $reverb = $this->supervisorChecker->getProgramStatus($reverbProgram);
        $queue = $this->supervisorChecker->getProgramStatus($queueProgram);

        return [
            'openclaw' => [
                'online' => $openclaw['online'],
                'status' => $openclaw['status'],
                'details' => $openclaw['details'],
                'cpu_percent' => $openclaw['cpu_percent'],
                'memory_percent' => $openclaw['memory_percent'],
                'disk_percent' => $openclaw['disk_percent'],
            ],
            'reverb' => [
                'status' => $reverb['status'],
                'raw_state' => $reverb['raw_state'],
                'details' => $reverb['details'],
            ],
            'queue' => [
                'status' => $queue['status'],
                'raw_state' => $queue['raw_state'],
                'details' => $queue['details'],
            ],
        ];
    }
}
