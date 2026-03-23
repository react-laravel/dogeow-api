<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\SupervisorStatusChecker;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupervisorStatusCheckerTest extends TestCase
{
    protected SupervisorStatusChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new SupervisorStatusChecker;
    }

    #[Test]
    public function test_get_program_status_returns_error_for_empty_program_name(): void
    {
        $result = $this->checker->getProgramStatus('');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('UNKNOWN', $result['raw_state']);
        $this->assertEquals('未配置进程名', $result['details']);
    }

    #[Test]
    public function test_get_program_status_returns_error_for_whitespace_only_program_name(): void
    {
        $result = $this->checker->getProgramStatus('   ');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('UNKNOWN', $result['raw_state']);
    }

    #[Test]
    public function test_get_program_status_uses_reverb_probe_pattern(): void
    {
        Config::set('services.supervisor.reverb_program', 'reverb');
        Config::set('services.supervisor.reverb_probe_pattern', 'artisan reverb:start --host=0.0.0.0');

        $mockChecker = $this->getMockBuilder(SupervisorStatusChecker::class)
            ->onlyMethods(['executeProcessProbe'])
            ->getMock();

        $mockChecker->expects($this->once())
            ->method('executeProcessProbe')
            ->with('artisan reverb:start --host=0.0.0.0')
            ->willReturn([
                'output' => '12345',
                'error' => '',
                'exitCode' => 0,
            ]);

        $result = $mockChecker->getProgramStatus('reverb');

        $this->assertEquals('online', $result['status']);
        $this->assertEquals('RUNNING', $result['raw_state']);
        $this->assertStringContainsString('PID 12345', $result['details']);
    }

    #[Test]
    public function test_get_program_status_uses_queue_probe_pattern(): void
    {
        Config::set('services.supervisor.queue_program', 'queue-default');
        Config::set('services.supervisor.queue_probe_pattern', 'artisan queue:work --queue=default');

        $mockChecker = $this->getMockBuilder(SupervisorStatusChecker::class)
            ->onlyMethods(['executeProcessProbe'])
            ->getMock();

        $mockChecker->expects($this->once())
            ->method('executeProcessProbe')
            ->with('artisan queue:work --queue=default')
            ->willReturn([
                'output' => '9999',
                'error' => '',
                'exitCode' => 0,
            ]);

        $result = $mockChecker->getProgramStatus('queue-default');

        $this->assertEquals('online', $result['status']);
        $this->assertEquals('RUNNING', $result['raw_state']);
    }

    #[Test]
    public function test_get_program_status_uses_program_name_as_default_pattern(): void
    {
        $mockChecker = $this->getMockBuilder(SupervisorStatusChecker::class)
            ->onlyMethods(['executeProcessProbe'])
            ->getMock();

        $mockChecker->expects($this->once())
            ->method('executeProcessProbe')
            ->with('my-custom-program')
            ->willReturn([
                'output' => '54321',
                'error' => '',
                'exitCode' => 0,
            ]);

        $result = $mockChecker->getProgramStatus('my-custom-program');

        $this->assertEquals('online', $result['status']);
        $this->assertEquals('RUNNING', $result['raw_state']);
    }

    #[Test]
    public function test_get_program_status_returns_offline_when_probe_not_matched(): void
    {
        $mockChecker = $this->getMockBuilder(SupervisorStatusChecker::class)
            ->onlyMethods(['executeProcessProbe'])
            ->getMock();

        $mockChecker->expects($this->once())
            ->method('executeProcessProbe')
            ->willReturn([
                'output' => '',
                'error' => '',
                'exitCode' => 1,
            ]);

        $result = $mockChecker->getProgramStatus('any-program');

        $this->assertEquals('offline', $result['status']);
        $this->assertEquals('STOPPED', $result['raw_state']);
        $this->assertStringContainsString('未发现运行中的进程', $result['details']);
    }

    #[Test]
    public function test_get_program_status_returns_error_for_unexpected_probe_exit_code(): void
    {
        $mockChecker = $this->getMockBuilder(SupervisorStatusChecker::class)
            ->onlyMethods(['executeProcessProbe'])
            ->getMock();

        $mockChecker->expects($this->once())
            ->method('executeProcessProbe')
            ->willReturn([
                'output' => '',
                'error' => 'pgrep execution failed',
                'exitCode' => 2,
            ]);

        $result = $mockChecker->getProgramStatus('any-program');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('UNKNOWN', $result['raw_state']);
        $this->assertStringContainsString('pgrep execution failed', $result['details']);
    }

    #[Test]
    public function test_get_program_status_returns_error_on_probe_exception(): void
    {
        $mockChecker = $this->getMockBuilder(SupervisorStatusChecker::class)
            ->onlyMethods(['executeProcessProbe'])
            ->getMock();

        $mockChecker->expects($this->once())
            ->method('executeProcessProbe')
            ->willThrowException(new \Exception('Process not found'));

        $result = $mockChecker->getProgramStatus('any-program');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('UNKNOWN', $result['raw_state']);
        $this->assertStringContainsString('Process not found', $result['details']);
    }

    #[Test]
    public function test_extract_first_pid_from_multiline_output(): void
    {
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('extractFirstPid');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, "12345\n67890\n");

        $this->assertEquals('12345', $result);
    }

    #[Test]
    public function test_sanitize_normalizes_whitespace(): void
    {
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('sanitize');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, "multiple   spaces\n\ttab", 100);

        $this->assertEquals('multiple spaces tab', $result);
    }

    #[Test]
    public function test_execute_process_probe_integration(): void
    {
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('executeProcessProbe');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, 'this-pattern-should-not-exist-1234567890');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('exitCode', $result);
        $this->assertIsString($result['output']);
        $this->assertIsString($result['error']);
        $this->assertIsInt($result['exitCode']);
    }
}
