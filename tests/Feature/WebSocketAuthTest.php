<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSocketAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_websocket_auth_middleware_requires_token(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        // auth:sanctum 对未认证请求返回 401
        $response->assertStatus(401);
    }

    public function test_websocket_auth_middleware_accepts_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(200);
    }

    public function test_websocket_auth_middleware_rejects_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
    }

    public function test_public_channels_require_auth_with_auth_sanctum(): void
    {
        // auth:sanctum 下所有请求均需认证，无 token 返回 401
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'chat.room.1',
        ]);

        $response->assertStatus(401);
    }

    public function test_websocket_auth_with_malformed_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer',
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
    }

    public function test_websocket_auth_without_authorization_header(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
    }

    public function test_websocket_auth_with_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['*'], now()->subDay())->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/broadcasting/auth', [
            'channel_name' => 'private-chat.room.1',
        ]);

        $response->assertStatus(401);
    }
}
