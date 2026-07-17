<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SsoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('sso.ticket_lifetime_seconds', 60);
        Config::set('sso.clients.rpg', [
            'secret' => 'rpg-secret',
            'callback_url' => 'https://rpg.dogeow.com/auth/callback',
            'return_origins' => ['https://rpg.dogeow.com'],
        ]);
        Config::set('sso.clients.game', [
            'secret' => 'game-secret',
            'callback_url' => 'https://game.dogeow.com/auth/callback',
            'return_origins' => ['https://game.dogeow.com'],
        ]);
        Config::set('sso.clients.chat', [
            'secret' => 'chat-secret',
            'callback_url' => 'https://chat.dogeow.com/auth/callback',
            'return_origins' => ['https://chat.dogeow.com'],
        ]);
        Config::set('sso.clients.mysql-compare', [
            'secret' => 'mysql-compare-secret',
            'callback_url' => 'https://mysql-compare.dogeow.com/auth/callback',
            'return_origins' => ['https://mysql-compare.dogeow.com'],
            'admin_only' => true,
        ]);
    }

    public function test_issue_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/sso/ticket', [
            'client' => 'rpg',
            'return_to' => 'https://rpg.dogeow.com/',
        ]);

        $response->assertStatus(401);
    }

    public function test_issue_validates_client_and_return_to(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/auth/sso/ticket', [
            'client' => 'unknown-client',
            'return_to' => 'not-a-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client', 'return_to']);
    }

    public function test_issue_rejects_disallowed_return_origin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/auth/sso/ticket', [
            'client' => 'rpg',
            'return_to' => 'https://evil.example/phish',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'SSO return URL is not allowed.');
    }

    public function test_issue_returns_redirect_url_for_authenticated_user(): void
    {
        Redis::shouldReceive('setex')
            ->once()
            ->with(
                Mockery::on(fn (string $key): bool => str_starts_with($key, 'sso:ticket:')),
                60,
                Mockery::type('string'),
            );

        Sanctum::actingAs(User::factory()->create([
            'name' => 'Sam',
            'is_admin' => false,
        ]));

        $response = $this->postJson('/api/auth/sso/ticket', [
            'client' => 'chat',
            'return_to' => 'https://chat.dogeow.com/rooms/1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'SSO ticket issued')
            ->assertJsonPath('data.expires_in', 60);

        $redirectUrl = $response->json('data.redirect_url');
        $this->assertIsString($redirectUrl);
        $this->assertStringStartsWith('https://chat.dogeow.com/auth/callback?', $redirectUrl);
        $this->assertStringContainsString(
            'return_to=https%3A%2F%2Fchat.dogeow.com%2Frooms%2F1',
            $redirectUrl
        );
    }

    public function test_issue_rejects_non_admin_for_admin_only_client(): void
    {
        Redis::shouldReceive('setex')->never();

        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $response = $this->postJson('/api/auth/sso/ticket', [
            'client' => 'mysql-compare',
            'return_to' => 'https://mysql-compare.dogeow.com/',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'SSO access is restricted to administrators.');
    }

    public function test_issue_allows_admin_for_admin_only_client(): void
    {
        Redis::shouldReceive('setex')->once();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $response = $this->postJson('/api/auth/sso/ticket', [
            'client' => 'mysql-compare',
            'return_to' => 'https://mysql-compare.dogeow.com/databases',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertStringStartsWith(
            'https://mysql-compare.dogeow.com/auth/callback?',
            (string) $response->json('data.redirect_url')
        );
    }

    public function test_exchange_rejects_invalid_ticket_shape(): void
    {
        $response = $this->postJson('/api/auth/sso/exchange', [
            'client' => 'rpg',
            'ticket' => 'too-short',
        ], [
            'X-SSO-Client-Secret' => 'rpg-secret',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_exchange_rejects_missing_or_wrong_secret(): void
    {
        Redis::shouldReceive('connection')->never();

        $response = $this->postJson('/api/auth/sso/exchange', [
            'client' => 'rpg',
            'ticket' => str_repeat('a', 64),
        ], [
            'X-SSO-Client-Secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid SSO client credentials.');
    }

    public function test_exchange_returns_identity_for_valid_ticket(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('getdel', Mockery::type('array'))
            ->andReturn(json_encode([
                'client' => 'rpg',
                'identity' => [
                    'id' => 7,
                    'name' => 'Sam',
                    'email' => 'sam@example.com',
                    'is_admin' => true,
                    'permissions' => ['admin'],
                ],
            ]));
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $response = $this->postJson('/api/auth/sso/exchange', [
            'client' => 'rpg',
            'ticket' => str_repeat('b', 64),
        ], [
            'X-SSO-Client-Secret' => 'rpg-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'SSO ticket exchanged')
            ->assertJsonPath('data.identity.id', 7)
            ->assertJsonPath('data.identity.name', 'Sam')
            ->assertJsonPath('data.identity.email', 'sam@example.com')
            ->assertJsonPath('data.identity.is_admin', true)
            ->assertJsonPath('data.identity.permissions', ['admin']);
    }

    public function test_exchange_rejects_expired_or_missing_ticket(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('getdel', Mockery::type('array'))
            ->andReturn(null);
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $response = $this->postJson('/api/auth/sso/exchange', [
            'client' => 'rpg',
            'ticket' => str_repeat('c', 64),
        ], [
            'X-SSO-Client-Secret' => 'rpg-secret',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'SSO ticket is invalid, expired, or already used.');
    }

    public function test_exchange_rejects_ticket_issued_for_another_client(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('getdel', Mockery::type('array'))
            ->andReturn(json_encode([
                'client' => 'game',
                'identity' => [
                    'id' => 1,
                    'name' => 'Sam',
                    'email' => null,
                    'is_admin' => false,
                    'permissions' => [],
                ],
            ]));
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $response = $this->postJson('/api/auth/sso/exchange', [
            'client' => 'rpg',
            'ticket' => str_repeat('d', 64),
        ], [
            'X-SSO-Client-Secret' => 'rpg-secret',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'SSO ticket payload is invalid.');
    }
}
