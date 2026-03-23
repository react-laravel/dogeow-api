<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\SystemStatusController;
use App\Services\SystemStatus\SystemStatusService;
use Mockery;
use Tests\TestCase;

class SystemStatusControllerUnitTest extends TestCase
{
    public function test_controller_can_be_instantiated(): void
    {
        $controller = $this->app->make(SystemStatusController::class);

        $this->assertInstanceOf(SystemStatusController::class, $controller);
    }

    public function test_index_returns_aggregated_status_payload(): void
    {
        $payload = [
            'openclaw' => ['online' => true, 'status' => 'online', 'details' => 'healthy'],
            'reverb' => ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'ok'],
            'queue' => ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'ok'],
        ];

        $service = Mockery::mock(SystemStatusService::class);
        $service->shouldReceive('getAggregatedStatus')->once()->andReturn($payload);

        $controller = new SystemStatusController($service);
        $response = $controller->index();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame($payload, json_decode($response->getContent(), true));
    }
}
