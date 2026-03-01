<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\SystemStatusController;
use Tests\TestCase;

class SystemStatusControllerUnitTest extends TestCase
{
    public function test_controller_can_be_instantiated(): void
    {
        $controller = $this->app->make(SystemStatusController::class);

        $this->assertInstanceOf(SystemStatusController::class, $controller);
    }
}
