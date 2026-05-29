<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\HermesStatusChecker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HermesStatusCheckerTest extends TestCase
{
    private const HEALTH_URL = 'http://127.0.0.1:18789/health';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.hermes.enabled', true);
        Config::set('services.hermes.health_url', self::HEALTH_URL);
        Config::set('services.hermes.host', '127.0.0.1');
        Config::set('services.hermes.port', 18789);
        Config::set('services.hermes.health_path', '/health');
        Config::set('services.hermes.timeout_seconds', 5);
        Config::set('services.hermes.use_host_metrics', false);
        Config::set('services.hermes.probe_pattern', 'openclaw-gateway-probe-test-pattern-no-match');
        Config::set('services.hermes.cli_binary', '');
    }

    public function test_returns_offline_when_disabled(): void
    {
        Config::set('services.hermes.enabled', false);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('offline', $result['status']);
        $this->assertStringContainsString('已禁用', $result['details']);
    }

    public function test_returns_error_on_http_failure(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response(null, 500),
            'http://127.0.0.1:18789/*' => Http::response(null, 500),
        ]);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('无法连接 Hermes', $result['details']);
    }

    public function test_returns_online_when_response_format_invalid_but_successful(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response('not json', 200),
        ]);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('online', $result['status']);
        $this->assertStringContainsString('Hermes 可访问', $result['details']);
    }

    public function test_parses_openclaw_ok_field(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'ok' => true,
                'durationMs' => 12,
            ], 200),
        ]);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('online', $result['status']);
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

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals(45.2, $result['cpu_percent']);
        $this->assertEquals(60.0, $result['memory_percent']);
        $this->assertEquals(30.5, $result['disk_percent']);
        $this->assertStringContainsString('CPU: 45.2%', $result['details']);
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

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals(50.0, $result['cpu_percent']);
        $this->assertEquals(50.0, $result['memory_percent']);
        $this->assertEquals(20.0, $result['disk_percent']);
    }

    public function test_resolves_warning_when_any_metric_above_90(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'ok' => true,
                'cpu_percent' => 92,
                'memory_percent' => 50,
            ], 200),
        ]);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('warning', $result['status']);
    }

    public function test_resolves_error_when_ok_false(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response([
                'ok' => false,
            ], 200),
        ]);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('error', $result['status']);
    }

    public function test_returns_error_on_connection_exception(): void
    {
        Http::fake(fn () => throw new \Exception('Connection refused'));

        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertFalse($result['online']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('无法连接 Hermes', $result['details']);
    }

    public function test_tries_root_path_when_health_path_fails(): void
    {
        Http::fake([
            self::HEALTH_URL => Http::response(null, 404),
            'http://127.0.0.1:18789/' => Http::response(['ok' => true], 200),
        ]);

        $checker = new HermesStatusChecker;
        $result = $checker->check();

        $this->assertTrue($result['online']);
        $this->assertEquals('online', $result['status']);
    }
}
