<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\CdnStatusChecker;
use App\Services\SystemStatus\DatabaseStatusChecker;
use App\Services\SystemStatus\GithubRateLimitChecker;
use App\Services\SystemStatus\OpenClawStatusChecker;
use App\Services\SystemStatus\RedisStatusChecker;
use App\Services\SystemStatus\SchedulerStatusChecker;
use App\Services\SystemStatus\SupervisorStatusChecker;
use App\Services\SystemStatus\SystemStatusService;
use Tests\TestCase;

class SystemStatusServiceTest extends TestCase
{
    private function makeService(
        array $openclaw,
        array $supervisor,
        array $database = ['status' => 'online', 'details' => 'db ok', 'response_time' => 1.2],
        array $redis = ['status' => 'online', 'details' => 'redis ok', 'response_time' => 0.8],
        array $cdn = ['status' => 'online', 'details' => 'cdn ok', 'response_time' => 12.4],
        array $scheduler = ['status' => 'online', 'details' => 'scheduler ok', 'last_run' => '2026-03-09T14:00:00+08:00'],
        array $github = ['status' => 'online', 'details' => '一小时 4314/6000，已用 1686；GraphQL 5000/5000，已用 0']
    ): SystemStatusService {
        $openclawChecker = $this->createMock(OpenClawStatusChecker::class);
        $openclawChecker->method('check')->willReturn($openclaw);

        $supervisorChecker = $this->createMock(SupervisorStatusChecker::class);
        $supervisorChecker->method('getProgramStatus')->willReturn($supervisor);

        $databaseChecker = $this->createMock(DatabaseStatusChecker::class);
        $databaseChecker->method('check')->willReturn($database);

        $redisChecker = $this->createMock(RedisStatusChecker::class);
        $redisChecker->method('check')->willReturn($redis);

        $cdnChecker = $this->createMock(CdnStatusChecker::class);
        $cdnChecker->method('check')->willReturn($cdn);

        $schedulerChecker = $this->createMock(SchedulerStatusChecker::class);
        $schedulerChecker->method('check')->willReturn($scheduler);

        $githubChecker = $this->createMock(GithubRateLimitChecker::class);
        $githubChecker->method('check')->willReturn($github);

        return new SystemStatusService(
            $openclawChecker,
            $supervisorChecker,
            $databaseChecker,
            $redisChecker,
            $cdnChecker,
            $schedulerChecker,
            $githubChecker
        );
    }

    public function test_service_can_be_instantiated(): void
    {
        $service = $this->makeService(
            ['online' => true, 'status' => 'running', 'details' => 'OpenClaw is running'],
            ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'Process is running']
        );

        $this->assertInstanceOf(SystemStatusService::class, $service);
    }

    public function test_get_aggregated_status_returns_array(): void
    {
        $service = $this->makeService(
            ['online' => true, 'status' => 'running', 'details' => 'OpenClaw is running'],
            ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'Process is running']
        );

        $result = $service->getAggregatedStatus();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openclaw', $result);
        $this->assertArrayHasKey('reverb', $result);
        $this->assertArrayHasKey('queue', $result);
        $this->assertArrayHasKey('github', $result);
    }

    public function test_get_aggregated_status_contains_openclaw_info(): void
    {
        $service = $this->makeService(
            [
                'online' => true,
                'status' => 'running',
                'details' => 'OpenClaw is running',
                'cpu_percent' => 10.5,
                'memory_percent' => 25.0,
                'disk_percent' => 50.0,
            ],
            ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'Process is running']
        );

        $result = $service->getAggregatedStatus();

        $this->assertTrue($result['openclaw']['online']);
        $this->assertEquals('running', $result['openclaw']['status']);
        $this->assertEquals(10.5, $result['openclaw']['cpu_percent']);
    }

    public function test_get_aggregated_status_contains_reverb_info(): void
    {
        $service = $this->makeService(
            [
                'online' => false,
                'status' => 'stopped',
                'details' => 'OpenClaw is stopped',
                'cpu_percent' => null,
                'memory_percent' => null,
                'disk_percent' => null,
            ],
            ['status' => 'stopped', 'raw_state' => 'STOPPED', 'details' => 'Process is stopped']
        );

        $result = $service->getAggregatedStatus();

        $this->assertEquals('stopped', $result['reverb']['status']);
        $this->assertEquals('STOPPED', $result['reverb']['raw_state']);
    }

    public function test_get_aggregated_status_contains_queue_info(): void
    {
        $service = $this->makeService(
            [
                'online' => false,
                'status' => 'stopped',
                'details' => 'OpenClaw is stopped',
                'cpu_percent' => null,
                'memory_percent' => null,
                'disk_percent' => null,
            ],
            ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'Queue is processing']
        );

        $result = $service->getAggregatedStatus();

        $this->assertEquals('running', $result['queue']['status']);
        $this->assertEquals('Queue is processing', $result['queue']['details']);
    }
}
