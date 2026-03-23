<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Arr;

/**
 * 聚合所有系统服务状态，返回前端所需的统一 DTO。
 */
class SystemStatusService
{
    public function __construct(
        private OpenClawStatusChecker $openclawChecker,
        private SupervisorStatusChecker $supervisorChecker,
        private DatabaseStatusChecker $databaseChecker,
        private RedisStatusChecker $redisChecker,
        private CdnStatusChecker $cdnChecker,
        private SchedulerStatusChecker $schedulerChecker,
        private GithubRateLimitChecker $githubRateLimitChecker
    ) {}

    /**
     * @return array{
     *   openclaw: array{online: bool, status: string, details: string, cpu_percent?: float, memory_percent?: float, disk_percent?: float},
     *   reverb: array{status: string, raw_state: string, details: string},
     *   queue: array{status: string, raw_state: string, details: string},
     *   database: array{status: string, details: string, response_time?: float},
     *   redis: array{status: string, details: string, response_time?: float},
     *   cdn: array{status: string, details: string, response_time?: float},
     *   scheduler: array{status: string, details: string, last_run?: string},
     *   github: array{status: string, details: string, core_remaining?: int|null, core_limit?: int|null, core_used?: int|null, graphql_remaining?: int|null, graphql_limit?: int|null, graphql_used?: int|null, reset_at?: string|null}
     * }
     */
    public function getAggregatedStatus(): array
    {
        $openclaw = $this->openclawChecker->check();
        $reverbProgram = config('services.supervisor.reverb_program', 'reverb');
        $queueProgram = config('services.supervisor.queue_program', 'queue-default');

        $reverb = $this->supervisorChecker->getProgramStatus($reverbProgram);
        $queue = $this->supervisorChecker->getProgramStatus($queueProgram);
        $database = $this->databaseChecker->check();
        $redis = $this->redisChecker->check();
        $cdn = $this->cdnChecker->check();
        $scheduler = $this->schedulerChecker->check();
        $github = $this->githubRateLimitChecker->check();

        return [
            'openclaw' => [
                'online' => Arr::get($openclaw, 'online', false),
                'status' => Arr::get($openclaw, 'status', 'error'),
                'details' => Arr::get($openclaw, 'details', '未知状态'),
                'cpu_percent' => Arr::get($openclaw, 'cpu_percent'),
                'memory_percent' => Arr::get($openclaw, 'memory_percent'),
                'disk_percent' => Arr::get($openclaw, 'disk_percent'),
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
            'database' => $database,
            'redis' => $redis,
            'cdn' => $cdn,
            'scheduler' => $scheduler,
            'github' => $github,
        ];
    }
}
