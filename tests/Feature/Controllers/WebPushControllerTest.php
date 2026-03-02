<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebPushControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure storage directories exist
        $paths = [
            storage_path('app'),
            storage_path('logs'),
        ];
        foreach ($paths as $path) {
            if (! file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    public function test_vapid_key_returns_public_key(): void
    {
        // Set the VAPID public key in config
        config(['webpush.public_key' => 'test-public-key-12345']);

        $response = $this->getJson('/api/webpush/vapid');

        $response->assertStatus(200);
        $response->assertJson([
            'public_key' => 'test-public-key-12345',
        ]);
    }

    public function test_vapid_key_returns_error_when_not_configured(): void
    {
        // Ensure no VAPID key is configured
        config(['webpush.public_key' => null]);

        $response = $this->getJson('/api/webpush/vapid');

        $response->assertStatus(500);
        $response->assertJsonStructure([
            'message',
        ]);
    }

    public function test_vapid_key_is_public_without_authentication(): void
    {
        config(['webpush.public_key' => 'test-public-key']);

        $response = $this->getJson('/api/webpush/vapid');

        $response->assertStatus(200);
    }

    public function test_update_subscription_success(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key',
            ],
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => '推送订阅已保存',
        ]);
    }

    public function test_update_subscription_requires_authentication(): void
    {
        $subscriptionData = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key',
            ],
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(401);
    }

    public function test_update_subscription_validates_endpoint_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key',
            ],
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_update_subscription_validates_endpoint_max_length(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'endpoint' => str_repeat('a', 2001), // Exceeds max 2000
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key',
            ],
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_update_subscription_validates_keys_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['keys']);
    }

    public function test_update_subscription_validates_p256dh_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'auth' => 'test-auth-key',
            ],
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['keys.p256dh']);
    }

    public function test_update_subscription_validates_auth_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $subscriptionData = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
            ],
        ];

        $response = $this->postJson('/api/user/push-subscription', $subscriptionData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['keys.auth']);
    }

    public function test_delete_subscription_success(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint';

        $response = $this->deleteJson('/api/user/push-subscription', [
            'endpoint' => $endpoint,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => '推送订阅已删除',
        ]);
    }

    public function test_delete_subscription_requires_authentication(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint';

        $response = $this->deleteJson('/api/user/push-subscription', [
            'endpoint' => $endpoint,
        ]);

        $response->assertStatus(401);
    }

    public function test_delete_subscription_validates_endpoint_required(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user/push-subscription', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_delete_subscription_validates_endpoint_max_length(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user/push-subscription', [
            'endpoint' => str_repeat('a', 2001), // Exceeds max 2000
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_delete_subscription_validates_endpoint_string(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user/push-subscription', [
            'endpoint' => ['not', 'a', 'string'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }
}
