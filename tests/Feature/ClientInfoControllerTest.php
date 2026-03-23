<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientInfoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_client_info_returns_correct_structure(): void
    {
        // Mock the HTTP response from ip-api.com
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'country' => 'China',
                'regionName' => 'Beijing',
                'city' => 'Beijing',
                'isp' => 'China Mobile',
                'timezone' => 'Asia/Shanghai',
            ], 200),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200)
            ->assertJsonStructure([
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

        $responseData = $response->json();

        // Verify IP is present
        $this->assertNotEmpty($responseData['ip']);

        // Verify user agent is present
        $this->assertNotEmpty($responseData['user_agent']);

        // Verify location data
        $this->assertEquals('China', $responseData['location']['country']);
        $this->assertEquals('Beijing', $responseData['location']['region']);
        $this->assertEquals('Beijing', $responseData['location']['city']);
        $this->assertEquals('China Mobile', $responseData['location']['isp']);
        $this->assertEquals('Asia/Shanghai', $responseData['location']['timezone']);
    }

    public function test_get_client_info_handles_missing_location_data(): void
    {
        // Mock HTTP response with missing data
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'country' => 'China',
                // Missing other fields
            ], 200),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        $this->assertEquals('China', $responseData['location']['country']);
        $this->assertNull($responseData['location']['region']);
        $this->assertNull($responseData['location']['city']);
        $this->assertNull($responseData['location']['isp']);
        $this->assertNull($responseData['location']['timezone']);
    }

    public function test_get_client_info_handles_api_failure(): void
    {
        // Mock HTTP response failure
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should still return IP and user agent
        $this->assertNotEmpty($responseData['ip']);
        $this->assertNotEmpty($responseData['user_agent']);

        // Location data should be null
        $this->assertNull($responseData['location']['country']);
        $this->assertNull($responseData['location']['region']);
        $this->assertNull($responseData['location']['city']);
        $this->assertNull($responseData['location']['isp']);
        $this->assertNull($responseData['location']['timezone']);
    }

    public function test_get_client_info_handles_network_timeout(): void
    {
        // Mock HTTP timeout
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([], 408),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should still return basic info
        $this->assertNotEmpty($responseData['ip']);
        $this->assertNotEmpty($responseData['user_agent']);

        // Location data should be null due to timeout
        $this->assertNull($responseData['location']['country']);
    }

    public function test_get_client_info_handles_empty_response(): void
    {
        // Mock empty response
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([], 200),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should return basic info
        $this->assertNotEmpty($responseData['ip']);
        $this->assertNotEmpty($responseData['user_agent']);

        // All location fields should be null
        $this->assertNull($responseData['location']['country']);
        $this->assertNull($responseData['location']['region']);
        $this->assertNull($responseData['location']['city']);
        $this->assertNull($responseData['location']['isp']);
        $this->assertNull($responseData['location']['timezone']);
    }

    public function test_get_client_info_handles_partial_location_data(): void
    {
        // Mock response with only some location data
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'country' => 'United States',
                'city' => 'New York',
                'timezone' => 'America/New_York',
                // Missing region and isp
            ], 200),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        $this->assertEquals('United States', $responseData['location']['country']);
        $this->assertNull($responseData['location']['region']);
        $this->assertEquals('New York', $responseData['location']['city']);
        $this->assertNull($responseData['location']['isp']);
        $this->assertEquals('America/New_York', $responseData['location']['timezone']);
    }

    public function test_get_client_info_returns_correct_ip(): void
    {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'country' => 'Test Country',
            ], 200),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should return a valid IP format
        $this->assertMatchesRegularExpression(
            '/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$|^::1$|^127\.0\.0\.1$/',
            $responseData['ip']
        );
    }

    public function test_get_client_info_returns_user_agent(): void
    {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'country' => 'Test Country',
            ], 200),
        ]);

        $response = $this->getJson('/api/client-info');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should return user agent (might be empty in tests but should be present)
        $this->assertArrayHasKey('user_agent', $responseData);
    }

    public function test_get_client_basic_info_returns_ip_and_user_agent_without_location_lookup(): void
    {
        Http::fake();

        $response = $this->withHeaders([
            'User-Agent' => 'CodexTest/1.0',
        ])->getJson('/api/client-basic-info');

        $response->assertOk()
            ->assertJson([
                'user_agent' => 'CodexTest/1.0',
            ])
            ->assertJsonStructure([
                'ip',
                'user_agent',
            ]);

        Http::assertNothingSent();
    }

    public function test_get_client_location_info_returns_location_payload(): void
    {
        Http::fake([
            'http://ip-api.com/json/*' => Http::response([
                'country' => 'Japan',
                'regionName' => 'Tokyo',
                'city' => 'Tokyo',
                'isp' => 'Example ISP',
                'timezone' => 'Asia/Tokyo',
            ], 200),
        ]);

        $response = $this->getJson('/api/client-location-info');

        $response->assertOk()
            ->assertJson([
                'location' => [
                    'country' => 'Japan',
                    'region' => 'Tokyo',
                    'city' => 'Tokyo',
                    'isp' => 'Example ISP',
                    'timezone' => 'Asia/Tokyo',
                ],
            ]);
    }

    public function test_get_client_location_info_returns_500_when_lookup_throws(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('ip lookup failed');
        });

        $response = $this->getJson('/api/client-location-info');

        $response->assertStatus(500)
            ->assertJson([
                'error' => '地理位置信息获取失败',
                'location' => [
                    'country' => null,
                    'region' => null,
                    'city' => null,
                    'isp' => null,
                    'timezone' => null,
                ],
            ]);
    }
}
