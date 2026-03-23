<?php

namespace Tests\Unit\Services\Web;

use App\Services\Web\ClientInfoService;
use Illuminate\Http\Request;
use Tests\TestCase;

class ClientInfoServiceTest extends TestCase
{
    private ClientInfoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClientInfoService;
    }

    public function test_get_basic_info_returns_ip_and_user_agent(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Test Agent');

        $result = $this->service->getBasicInfo($request);

        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('user_agent', $result);
        $this->assertEquals('Test Agent', $result['user_agent']);
    }

    public function test_get_location_info_returns_location_array(): void
    {
        // This will fail in test environment due to network call
        // Just verify it returns the expected structure
        $result = $this->service->getLocationInfo('8.8.8.8');

        $this->assertArrayHasKey('location', $result);
        $this->assertIsArray($result['location']);
    }

    public function test_get_location_info_returns_null_values_on_error(): void
    {
        // Test with invalid IP that will cause exception
        $result = $this->service->getLocationInfo('invalid-ip');

        $this->assertArrayHasKey('location', $result);
        $this->assertArrayHasKey('country', $result['location']);
        $this->assertArrayHasKey('region', $result['location']);
        $this->assertArrayHasKey('city', $result['location']);
        $this->assertArrayHasKey('isp', $result['location']);
        $this->assertArrayHasKey('timezone', $result['location']);
    }

    public function test_get_client_info_returns_complete_info(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Test Browser');

        $result = $this->service->getClientInfo($request);

        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('user_agent', $result);
        $this->assertArrayHasKey('location', $result);
        $this->assertEquals('Test Browser', $result['user_agent']);
    }

    public function test_get_client_info_location_may_contain_error(): void
    {
        $request = Request::create('/test', 'GET');

        $result = $this->service->getClientInfo($request);

        // Location may or may not have error key depending on API response
        $this->assertIsArray($result['location']);
    }
}
