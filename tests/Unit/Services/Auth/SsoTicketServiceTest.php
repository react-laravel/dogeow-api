<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\SsoTicketService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SsoTicketServiceTest extends TestCase
{
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
        Config::set('sso.clients.repo-watch', [
            'secret' => 'repo-watch-secret',
            'callback_url' => 'https://repo-watch.dogeow.com/auth/callback',
            'return_origins' => ['https://repo-watch.dogeow.com'],
        ]);
        Config::set('sso.clients.mysql-compare', [
            'secret' => 'mysql-compare-secret',
            'callback_url' => 'https://mysql-compare.dogeow.com/auth/callback',
            'return_origins' => ['https://mysql-compare.dogeow.com'],
            'admin_only' => true,
        ]);
        Config::set('sso.clients.knowledge-graph', [
            'callback_url' => 'https://mind.dogeow.com/',
            'return_origins' => ['https://mind.dogeow.com'],
            'public_client' => true,
            'issue_api_token' => true,
        ]);
    }

    public function test_it_issues_a_short_lived_ticket_for_an_allowed_return_url(): void
    {
        Redis::shouldReceive('setex')
            ->once()
            ->with(
                Mockery::on(fn (string $key): bool => str_starts_with($key, 'sso:ticket:')),
                60,
                Mockery::on(function (string $payload): bool {
                    $identity = json_decode($payload, true)['identity'];

                    return $identity['id'] === 42
                        && $identity['is_admin'] === true
                        && $identity['permissions'] === ['admin'];
                }),
            );

        $user = new User;
        $user->forceFill([
            'id' => 42,
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'is_admin' => true,
        ]);

        $result = app(SsoTicketService::class)->issue('rpg', 'https://rpg.dogeow.com/inventory', $user);
        parse_str((string) parse_url($result['redirect_url'], PHP_URL_QUERY), $query);

        $this->assertSame(60, $result['expires_in']);
        $this->assertSame('https://rpg.dogeow.com/inventory', $query['return_to']);
        $this->assertSame(64, strlen($query['ticket']));
    }

    public function test_it_rejects_an_unapproved_return_origin(): void
    {
        Redis::shouldReceive('setex')->never();

        $user = new User;
        $user->forceFill(['id' => 1, 'name' => 'Sam', 'is_admin' => false]);

        $this->expectException(InvalidArgumentException::class);
        app(SsoTicketService::class)->issue('rpg', 'https://evil.example/callback', $user);
    }

    public function test_it_issues_a_game_ticket_with_the_game_callback(): void
    {
        Redis::shouldReceive('setex')->once();

        $user = new User;
        $user->forceFill(['id' => 42, 'name' => 'Sam', 'is_admin' => false]);

        $result = app(SsoTicketService::class)->issue('game', 'https://game.dogeow.com/2048', $user);

        $this->assertStringStartsWith('https://game.dogeow.com/auth/callback?', $result['redirect_url']);
        $this->assertStringContainsString('return_to=https%3A%2F%2Fgame.dogeow.com%2F2048', $result['redirect_url']);
    }

    public function test_it_issues_a_repo_watch_ticket_with_the_repo_watch_callback(): void
    {
        Redis::shouldReceive('setex')->once();

        $user = new User;
        $user->forceFill(['id' => 42, 'name' => 'Sam', 'is_admin' => false]);

        $result = app(SsoTicketService::class)->issue(
            'repo-watch',
            'https://repo-watch.dogeow.com/',
            $user
        );

        $this->assertStringStartsWith(
            'https://repo-watch.dogeow.com/auth/callback?',
            $result['redirect_url']
        );
    }

    public function test_it_issues_an_admin_only_mysql_compare_ticket(): void
    {
        Redis::shouldReceive('setex')->once();

        $user = new User;
        $user->forceFill(['id' => 42, 'name' => 'Sam', 'is_admin' => true]);

        $result = app(SsoTicketService::class)->issue(
            'mysql-compare',
            'https://mysql-compare.dogeow.com/databases',
            $user,
        );

        $this->assertStringStartsWith('https://mysql-compare.dogeow.com/auth/callback?', $result['redirect_url']);
        $this->assertStringContainsString(
            'return_to=https%3A%2F%2Fmysql-compare.dogeow.com%2Fdatabases',
            $result['redirect_url'],
        );
    }

    public function test_it_rejects_non_admin_mysql_compare_access(): void
    {
        Redis::shouldReceive('setex')->never();

        $user = new User;
        $user->forceFill(['id' => 42, 'name' => 'Sam', 'is_admin' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted to administrators');

        app(SsoTicketService::class)->issue(
            'mysql-compare',
            'https://mysql-compare.dogeow.com/',
            $user,
        );
    }

    public function test_it_atomically_consumes_a_ticket_and_checks_the_client_secret(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('getdel', Mockery::type('array'))
            ->andReturn(json_encode([
                'client' => 'rpg',
                'identity' => [
                    'id' => 42,
                    'name' => 'Sam',
                    'email' => null,
                    'is_admin' => false,
                    'permissions' => [],
                ],
            ]));
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $identity = app(SsoTicketService::class)->exchange('rpg', str_repeat('a', 64), 'rpg-secret');

        $this->assertSame(42, $identity['id']);
        $this->assertFalse($identity['is_admin']);
    }

    public function test_it_rejects_an_invalid_client_secret_before_consuming_the_ticket(): void
    {
        Redis::shouldReceive('connection')->never();

        $this->expectException(RuntimeException::class);
        app(SsoTicketService::class)->exchange('rpg', str_repeat('a', 64), 'wrong-secret');
    }

    public function test_public_client_requires_a_pkce_challenge_when_issuing(): void
    {
        Redis::shouldReceive('setex')->never();

        $user = new User;
        $user->forceFill(['id' => 42, 'name' => 'Sam', 'is_admin' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PKCE challenge');

        app(SsoTicketService::class)->issue(
            'knowledge-graph',
            'https://mind.dogeow.com/',
            $user,
        );
    }

    public function test_public_client_exchanges_with_the_matching_pkce_verifier(): void
    {
        $verifier = str_repeat('v', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('getdel', Mockery::type('array'))
            ->andReturn(json_encode([
                'client' => 'knowledge-graph',
                'identity' => [
                    'id' => 42,
                    'name' => 'Sam',
                    'email' => null,
                    'is_admin' => false,
                    'permissions' => [],
                ],
                'code_challenge' => $challenge,
            ]));
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $identity = app(SsoTicketService::class)->exchange(
            'knowledge-graph',
            str_repeat('f', 64),
            null,
            $verifier,
        );

        $this->assertSame(42, $identity['id']);
    }
}
