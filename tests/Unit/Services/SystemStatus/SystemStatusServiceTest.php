<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\OpenClawStatusChecker;
use App\Services\SystemStatus\SupervisorStatusChecker;
use App\Services\SystemStatus\SystemStatusService;
use Tests\TestCase;

class SystemStatusServiceTest extends TestCase
{
    public function test_service_can_be_instantiated(): void
    {
        $openclawChecker = $this->createMock(OpenClawStatusChecker::class);
        $supervisorChecker = $this->createMock(SupervisorStatusChecker::class);

        $service = new SystemStatusService($openclawChecker, $supervisorChecker);

        $this->assertInstanceOf(SystemStatusService::class, $service);
    }

    public function test_get_aggregated_status_returns_array(): void
    {
        $openclawChecker = $this->createMock(OpenClawStatusChecker::class);
        $openclawChecker->method('check')->willReturn([
            'online' => true,
            'status' => 'running',
            'details' => 'OpenClaw is running',
            'cpu_percent' => 10.5,
            'memory_percent' => 25.0,
            'disk_percent' => 50.0,
        ]);

        $supervisorChecker = $this->createMock(SupervisorStatusChecker::class);
        $supervisorChecker->method('getProgramStatus')->willReturn([
            'status' => 'running',
            'raw_state' => 'RUNNING',
            'details' => 'Process is running',
        ]);

        $service = new SystemStatusService($openclawChecker, $supervisorChecker);
        $result = $service->getAggregatedStatus();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openclaw', $result);
        $this->assertArrayHasKey('reverb', $result);
        $this->assertArrayHasKey('queue', $result);
    }

    public function test_get_aggregated_status_contains_openclaw_info(): void
    {
        $openclawChecker = $this->createMock(OpenClawStatusChecker::class);
        $openclawChecker->method('check')->willReturn([
            'online' => true,
            'status' => 'running',
            'details' => 'OpenClaw is running',
            'cpu_percent' => 10.5,
            'memory_percent' => 25.0,
            'disk_percent' => 50.0,
        ]);

        $supervisorChecker = $this->createMock(SupervisorStatusChecker::class);
        $supervisorChecker->method('getProgramStatus')->willReturn([
            'status' => 'running',
            'raw_state' => 'RUNNING',
            'details' => 'Process is running',
        ]);

        $service = new SystemStatusService($openclawChecker, $supervisorChecker);
        $result = $service->getAggregatedStatus();

        $this->assertTrue($result['openclaw']['online']);
        $this->assertEquals('running', $result['openclaw']['status']);
        $this->assertEquals(10.5, $result['openclaw']['cpu_percent']);
    }

    public function test_get_aggregated_status_contains_reverb_info(): void
    {
        $openclawChecker = $this->createMock(OpenClawStatusChecker::class);
        $openclawChecker->method('check')->willReturn([
            'online' => false,
            'status' => 'stopped',
            'details' => 'OpenClaw is stopped',
            'cpu_percent' => null,
            'memory_percent' => null,
            'disk_percent' => null,
        ]);

        $supervisorChecker = $this->createMock(SupervisorStatusChecker::class);
        $supervisorChecker->method('getProgramStatus')->willReturn([
            'status' => 'stopped',
            'raw_state' => 'STOPPED',
            'details' => 'Process is stopped',
        ]);

        $service = new SystemStatusService($openclawChecker, $supervisorChecker);
        $result = $service->getAggregatedStatus();

        $this->assertEquals('stopped', $result['reverb']['status']);
        $this->assertEquals('STOPPED', $result['reverb']['raw_state']);
    }

    public function test_get_aggregated_status_contains_queue_info(): void
    {
        $openclawChecker = $this->createMock(OpenClawStatusChecker::class);
        $openclawChecker->method('check')->willReturn([
            'online' => false,
            'status' => 'stopped',
            'details' => 'OpenClaw is stopped',
            'cpu_percent' => null,
            'memory_percent' => null,
            'disk_percent' => null,
        ]);

        $supervisorChecker = $this->createMock(SupervisorStatusChecker::class);
        $supervisorChecker->method('getProgramStatus')->willReturn([
            'status' => 'running',
            'raw_state' => 'RUNNING',
            'details' => 'Queue is processing',
        ]);

        $service = new SystemStatusService($openclawChecker, $supervisorChecker);
        $result = $service->getAggregatedStatus();

        $this->assertEquals('running', $result['queue']['status']);
        $this->assertEquals('Queue is processing', $result['queue']['details']);
    }
}
