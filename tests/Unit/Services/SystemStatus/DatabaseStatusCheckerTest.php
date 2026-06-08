<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\DatabaseStatusChecker;
use Illuminate\Support\Facades\DB;
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
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('driver', $result);
        $this->assertNotSame('', $result['label']);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertIsFloat($result['response_time']);
        $this->assertGreaterThanOrEqual(0, $result['response_time']);
    }

    public function test_check_returns_error_on_connection_failure(): void
    {
        // Mock a database connection failure
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \PDOException('Connection refused'));

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('数据库连接失败', $result['details']);
    }

    public function test_check_includes_response_time(): void
    {
        $result = $this->checker->check();

        $this->assertArrayHasKey('response_time', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertStringContainsString('ms', $result['details']);
    }

    public function test_check_returns_online_when_pdo_available(): void
    {
        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertSame('online', $result['status']);
    }

    public function test_check_response_time_is_reasonable(): void
    {
        $result = $this->checker->check();

        // Response time should be a positive number with 2 decimal places
        $this->assertIsFloat($result['response_time']);
        $this->assertGreaterThan(0, $result['response_time']);
        $this->assertLessThan(60000, $result['response_time']); // Should be under 60 seconds
    }
}
