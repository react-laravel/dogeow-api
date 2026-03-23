<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientInfoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_client_info_returns_correct_structure()
    {
        $ipInfo = [
            'country' => 'China',
            'regionName' => 'Beijing',
            'city' => 'Beijing',
            'isp' => 'China Mobile',
            'timezone' => 'Asia/Shanghai',
        ];

        Http::fake([
            'http://ip-api.com/json/*' => Http::response($ipInfo, 200),
        ]);

        $response = $this->get('/api/client-info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ip',
            'user_agent',
            'location' => [
                'country',
                'region',
                'city',
                'isp',
                'timezone',
            ],
        ]);
    }

    public function test_get_client_info_handles_missing_ip_info()
    {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([], 200),
        ]);

        $response = $this->get('/api/client-info');

        $response->assertStatus(200);
        $response->assertJson([
            'location' => [
                'country' => null,
                'region' => null,
                'city' => null,
                'isp' => null,
                'timezone' => null,
            ],
        ]);
    }

    public function test_get_client_info_handles_api_failure()
    {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([], 500),
        ]);

        $response = $this->get('/api/client-info');

        $response->assertStatus(200);
        $response->assertJson([
            'location' => [
                'country' => null,
                'region' => null,
                'city' => null,
                'isp' => null,
                'timezone' => null,
            ],
        ]);
    }

    public function test_get_client_info_returns_user_agent()
    {
        $ipInfo = [
            'country' => 'China',
            'regionName' => 'Beijing',
            'city' => 'Beijing',
            'isp' => 'China Mobile',
            'timezone' => 'Asia/Shanghai',
        ];

        Http::fake([
            'http://ip-api.com/json/*' => Http::response($ipInfo, 200),
        ]);

        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ])->get('/api/client-info');

        $response->assertStatus(200);
        $response->assertJson([
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
    }

    public function test_get_client_info_returns_ip_address()
    {
        $ipInfo = [
            'country' => 'China',
            'regionName' => 'Beijing',
            'city' => 'Beijing',
            'isp' => 'China Mobile',
            'timezone' => 'Asia/Shanghai',
        ];

        Http::fake([
            'http://ip-api.com/json/*' => Http::response($ipInfo, 200),
        ]);

        $response = $this->get('/api/client-info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ip',
        ]);
    }
}
