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
    }

    public function test_it_issues_a_short_lived_ticket_for_an_allowed_return_url(): void
    {
        Redis::shouldReceive('setex')
            ->once()
            ->with(
                Mockery::on(fn (string $key): bool => str_starts_with($key, 'sso:ticket:')),
                60,
                Mockery::on(fn (string $payload): bool => json_decode($payload, true)['identity']['id'] === 42),
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
}
