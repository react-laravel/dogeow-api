<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\CdnStatusChecker;
use Tests\TestCase;

class CdnStatusCheckerTest extends TestCase
{
    protected CdnStatusChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new CdnStatusChecker;
    }

    public function test_check_returns_warning_when_cdn_url_not_configured(): void
    {
        config(['services.upyun.cdn_url' => null]);

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('CDN URL 未配置', $result['details']);
    }

    public function test_check_returns_online_when_cdn_is_reachable(): void
    {
        // TODO: Implement test
    }

    public function test_check_returns_warning_when_cdn_returns_non_success(): void
    {
        // TODO: Implement test
    }

    public function test_check_returns_error_on_connection_failure(): void
    {
        // TODO: Implement test
    }

    public function test_check_includes_response_time_on_success(): void
    {
        // TODO: Implement test
    }
}
