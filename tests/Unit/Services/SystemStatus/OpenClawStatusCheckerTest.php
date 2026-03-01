<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\OpenClawStatusChecker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenClawStatusCheckerTest extends TestCase
{
    private const HEALTH_URL = 'https://openclaw.example.com/health';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.openclaw.health_url', self::HEALTH_URL);
        Config::set('services.openclaw.timeout_seconds', 5);
    }

    public function test_returns_offline_when_url_not_configured(): void
    {
        Config::set('services.openclaw.health_url', '');

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('offline', $result['status']);
        $this->assertNull($result['cpu_percent']);
        $this->assertNull($result['memory_percent']);
        $this->assertNull($result['disk_percent']);
        $this->assertStringContainsString('未配置', $result['details']);
    }

    public function test_returns_error_on_http_failure(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response(null, 500),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('HTTP 500', $result['details']);
    }

    public function test_returns_online_when_response_format_invalid_but_successful(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response('not json', 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('online', $result['status']);
        $this->assertStringContainsString('响应格式无效', $result['details']);
    }

    public function test_parses_percent_fields_directly(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => true,
                'cpu_percent' => 45.2,
                'memory_percent' => 60.0,
                'disk_percent' => 30.5,
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals(45.2, $result['cpu_percent']);
        $this->assertEquals(60.0, $result['memory_percent']);
        $this->assertEquals(30.5, $result['disk_percent']);
        $this->assertStringContainsString('CPU: 45.2%', $result['details']);
        $this->assertStringContainsString('内存: 60%', $result['details']);
        $this->assertStringContainsString('磁盘: 30.5%', $result['details']);
    }

    public function test_parses_used_total_and_converts_to_percent(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => true,
                'cpu' => ['used' => 50, 'total' => 100],
                'memory' => ['used' => 512, 'total' => 1024],
                'disk' => ['used' => 100, 'total' => 500],
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals(50.0, $result['cpu_percent']);
        $this->assertEquals(50.0, $result['memory_percent']);
        $this->assertEquals(20.0, $result['disk_percent']);
    }

    public function test_clamps_percent_between_0_and_100(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => true,
                'cpu_percent' => 150,
                'memory_percent' => -10,
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertEquals(100.0, $result['cpu_percent']);
        $this->assertEquals(0.0, $result['memory_percent']);
    }

    public function test_resolves_warning_when_any_metric_above_90(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => true,
                'cpu_percent' => 92,
                'memory_percent' => 50,
                'disk_percent' => 30,
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('warning', $result['status']);
    }

    public function test_resolves_error_when_any_metric_above_98(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => true,
                'cpu_percent' => 50,
                'memory_percent' => 99,
                'disk_percent' => 30,
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('error', $result['status']);
    }

    public function test_resolves_error_when_online_false(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => false,
                'cpu_percent' => 10,
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('error', $result['status']);
    }

    public function test_includes_message_in_details(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'online' => true,
                'message' => 'Server under maintenance',
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertStringContainsString('Server under maintenance', $result['details']);
    }

    public function test_returns_error_on_connection_exception(): void
    {
        Http::fake(fn () => throw new \Exception('Connection refused'));

        Log::shouldReceive('warning')->once();

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('无法连接', $result['details']);
        $this->assertStringContainsString('Connection refused', $result['details']);
    }

    public function test_defaults_online_true_when_not_in_response(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'cpu_percent' => 20,
            ], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('online', $result['status']);
    }

    public function test_returns_no_indicators_when_no_metrics(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response(['online' => true], 200),
        ]);

        $checker = new OpenClawStatusChecker;
        $result = $checker->check();

        $this->assertNull($result['cpu_percent']);
        $this->assertNull($result['memory_percent']);
        $this->assertNull($result['disk_percent']);
        $this->assertStringContainsString('无指标', $result['details']);
    }
}
