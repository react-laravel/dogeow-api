<?php

namespace Tests\Unit\Services;

use App\Services\Web\ClientInfoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_get_location_info_skips_lookup_for_private_ip(): void
    {
        Http::fake();

        $result = $this->service->getLocationInfo('127.0.0.1');

        Http::assertNothingSent();
        $this->assertSame([
            'country' => null,
            'region' => null,
            'city' => null,
            'isp' => null,
            'timezone' => null,
        ], $result['location']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_get_location_info_returns_provider_data_for_public_ip(): void
    {
        Http::fake([
            'http://ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'China',
                'regionName' => 'Fujian',
                'city' => 'Xiamen',
                'isp' => 'Example ISP',
                'timezone' => 'Asia/Shanghai',
            ]),
        ]);

        $result = $this->service->getLocationInfo('8.8.8.8');

        Http::assertSentCount(1);
        $this->assertSame('China', $result['location']['country']);
        $this->assertSame('Fujian', $result['location']['region']);
        $this->assertSame('Xiamen', $result['location']['city']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_get_location_info_returns_error_when_provider_fails(): void
    {
        Http::fake([
            'http://ip-api.com/*' => Http::response(['status' => 'fail'], 500),
        ]);

        $result = $this->service->getLocationInfo('8.8.8.8');

        Http::assertSentCount(1);
        $this->assertSame('地理位置信息获取失败', $result['error']);
        $this->assertSame([
            'country' => null,
            'region' => null,
            'city' => null,
            'isp' => null,
            'timezone' => null,
        ], $result['location']);
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
