<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\DatabaseStatusChecker;
use Tests\TestCase;

class DatabaseStatusCheckerTest extends TestCase
{
    protected DatabaseStatusChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new DatabaseStatusChecker;
    }

    public function test_check_returns_online_when_connection_works(): void
    {
        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertArrayHasKey('response_time', $result);
    }

    public function test_check_returns_error_on_connection_failure(): void
    {
        // TODO: Implement test
    }

    public function test_check_includes_response_time(): void
    {
        // TODO: Implement test
    }
}
