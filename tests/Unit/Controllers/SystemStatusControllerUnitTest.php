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

    public function test_index_returns_standardized_success_response(): void
    {
        $payload = [
            'hermes' => [
                'name' => '小龙虾🦞',
                'label' => 'Hermes',
                'online' => true,
                'status' => 'online',
                'details' => 'healthy',
            ],
            'reverb' => ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'ok'],
            'queue' => ['status' => 'running', 'raw_state' => 'RUNNING', 'details' => 'ok'],
        ];

        $service = Mockery::mock(SystemStatusService::class);
        $service->shouldReceive('getAggregatedStatus')->once()->andReturn($payload);

        $controller = new SystemStatusController($service);
        $response = $controller->index();
        $decoded = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($decoded['success']);
        $this->assertSame($payload, $decoded['data']);
    }
}
