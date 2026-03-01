<?php

namespace Tests\Unit\Services;

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
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $result = $this->service->getBasicInfo($request);

        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('user_agent', $result);
    }

    public function test_get_client_info_returns_full_info(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'Test Agent');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $result = $this->service->getClientInfo($request);

        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('user_agent', $result);
        $this->assertArrayHasKey('location', $result);
    }
}
