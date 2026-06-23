<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\Ai\MiniMaxController;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiniMaxControllerTest extends TestCase
{
    protected MiniMaxController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MiniMaxController;
    }

    public function test_subscription_returns_error_when_token_not_configured(): void
    {
        config(['services.minimax.token_api_key' => null]);

        $response = $this->controller->subscription();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_TOKEN_API_KEY', $data->message);
    }

    public function test_subscription_returns_subscription_data(): void
    {
        config(['services.minimax.token_api_key' => 'test_token_key']);

        Http::fake([
            'www.minimaxi.com/*' => Http::response(['subscription' => ['plan' => 'pro']], 200),
        ]);

        $response = $this->controller->subscription();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_subscription_detail_returns_error_when_token_not_configured(): void
    {
        config(['services.minimax.token_api_key' => null]);

        $response = $this->controller->subscriptionDetail();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_TOKEN_API_KEY', $data->message);
    }

    public function test_subscription_detail_returns_error_when_group_id_not_configured(): void
    {
        config(['services.minimax.token_api_key' => 'test_token_key']);
        config(['services.minimax.group_id' => null]);

        $response = $this->controller->subscriptionDetail();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_GROUP_ID', $data->message);
    }

    public function test_subscription_detail_returns_data(): void
    {
        config(['services.minimax.token_api_key' => 'test_token_key']);
        config(['services.minimax.group_id' => 'test_group_id']);

        Http::fake([
            'www.minimaxi.com/*' => Http::response(['package' => ['type' => 'audio']], 200),
        ]);

        $response = $this->controller->subscriptionDetail();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_billing_returns_error_when_api_key_not_configured(): void
    {
        config(['services.minimax.balance_api_key' => null]);
        config(['services.minimax.group_id' => 'test_group_id']);

        $response = $this->controller->billing();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_BALANCE_API_KEY', $data->message);
    }

    public function test_billing_returns_error_when_group_id_not_configured(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.group_id' => null]);

        $response = $this->controller->billing();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_GROUP_ID', $data->message);
    }

    public function test_billing_returns_billing_data(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.group_id' => 'test_group_id']);

        Http::fake([
            'www.minimaxi.com/*' => Http::response(['balance' => 100.50], 200),
        ]);

        $response = $this->controller->billing();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_get_token_api_key_returns_config_value(): void
    {
        config(['services.minimax.token_api_key' => 'my_token_key']);

        $apiKey = config('services.minimax.token_api_key');

        $this->assertSame('my_token_key', $apiKey);
    }

    public function test_get_balance_api_key_returns_config_value(): void
    {
        config(['services.minimax.balance_api_key' => 'my_balance_key']);

        $apiKey = config('services.minimax.balance_api_key');

        $this->assertSame('my_balance_key', $apiKey);
    }

    public function test_billing_redacts_sensitive_config_from_log(): void
    {
        $controller = new MiniMaxController;

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('redactMinimaxConfig');
        $method->setAccessible(true);

        $config = [
            'token_api_key' => 'secret_token',
            'balance_api_key' => 'secret_balance',
            'group_id' => 'test_group',
            'api_base_url' => 'https://www.minimaxi.com',
            'nested' => [
                'token_api_key' => 'nested_token',
                'other_key' => 'safe_value',
            ],
        ];

        $result = $method->invoke($controller, $config);

        $this->assertSame('***REDACTED***', $result['token_api_key']);
        $this->assertSame('***REDACTED***', $result['balance_api_key']);
        $this->assertSame('test_group', $result['group_id']);
        $this->assertSame('https://www.minimaxi.com', $result['api_base_url']);
        $this->assertSame('***REDACTED***', $result['nested']['token_api_key']);
        $this->assertSame('safe_value', $result['nested']['other_key']);
    }
}
