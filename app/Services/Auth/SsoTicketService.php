<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SsoTicketService
{
    private const KEY_PREFIX = 'sso:ticket:';

    /** @return array{redirect_url:string,expires_in:int} */
    public function issue(string $client, string $returnTo, User $user, ?string $codeChallenge = null): array
    {
        $configuration = $this->clientConfiguration($client);
        $this->assertAllowedReturnUrl($returnTo, $configuration['return_origins'] ?? []);

        if (($configuration['public_client'] ?? false) && $codeChallenge === null) {
            throw new InvalidArgumentException('SSO PKCE challenge is required.');
        }

        if (($configuration['admin_only'] ?? false) && ! $user->isAdmin()) {
            throw new RuntimeException('SSO access is restricted to administrators.');
        }

        $ticket = bin2hex(random_bytes(32));
        $lifetime = max(10, min(300, (int) config('sso.ticket_lifetime_seconds', 60)));
        $payload = json_encode([
            'client' => $client,
            'identity' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => $user->email !== null ? (string) $user->email : null,
                'is_admin' => $user->isAdmin(),
                // The central account model currently exposes one global role.
                // Carry it in both structured forms so standalone services can
                // share authorization decisions without a central SQL link.
                'permissions' => $user->isAdmin() ? ['admin'] : [],
            ],
            'code_challenge' => $codeChallenge,
        ], JSON_THROW_ON_ERROR);

        Redis::setex($this->ticketKey($ticket), $lifetime, $payload);

        $callbackUrl = (bool) ($configuration['use_return_to_as_callback'] ?? false)
            ? $returnTo
            : (string) ($configuration['callback_url'] ?? '');
        if ($callbackUrl === '') {
            throw new RuntimeException('SSO callback is not configured.');
        }

        $separator = str_contains($callbackUrl, '?') ? '&' : '?';

        return [
            'redirect_url' => $callbackUrl . $separator . http_build_query([
                'ticket' => $ticket,
                'return_to' => $returnTo,
            ], '', '&', PHP_QUERY_RFC3986),
            'expires_in' => $lifetime,
        ];
    }

    /** @return array{id:int,name:string,email:string|null,is_admin:bool,permissions:array<int,string>} */
    public function exchange(
        string $client,
        string $ticket,
        ?string $providedSecret,
        ?string $codeVerifier = null,
    ): array {
        $configuration = $this->clientConfiguration($client);
        $isPublicClient = (bool) ($configuration['public_client'] ?? false);

        if (! $isPublicClient) {
            $expectedSecret = (string) ($configuration['secret'] ?? '');
            if ($expectedSecret === '' || $providedSecret === null || ! hash_equals($expectedSecret, $providedSecret)) {
                throw new RuntimeException('Invalid SSO client credentials.');
            }
        }

        $payload = $this->consume($this->ticketKey($ticket));
        if (! is_string($payload) || $payload === '') {
            throw new RuntimeException('SSO ticket is invalid, expired, or already used.');
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ($decoded['client'] ?? null) !== $client || ! is_array($decoded['identity'] ?? null)) {
            throw new RuntimeException('SSO ticket payload is invalid.');
        }

        if ($isPublicClient) {
            $expectedChallenge = $decoded['code_challenge'] ?? null;
            $actualChallenge = $codeVerifier !== null ? $this->pkceChallenge($codeVerifier) : null;
            if (! is_string($expectedChallenge) || $actualChallenge === null || ! hash_equals($expectedChallenge, $actualChallenge)) {
                throw new RuntimeException('Invalid SSO PKCE verifier.');
            }
        }

        $identity = $decoded['identity'];
        if (! isset($identity['id'], $identity['name'])) {
            throw new RuntimeException('SSO identity is invalid.');
        }

        return [
            'id' => (int) $identity['id'],
            'name' => (string) $identity['name'],
            'email' => isset($identity['email']) ? (string) $identity['email'] : null,
            'is_admin' => (bool) ($identity['is_admin'] ?? false),
            'permissions' => array_values(array_map('strval', $identity['permissions'] ?? [])),
        ];
    }

    /** @return array<string,mixed> */
    private function clientConfiguration(string $client): array
    {
        $configuration = config("sso.clients.{$client}");
        if (! is_array($configuration)) {
            throw new InvalidArgumentException('Unknown SSO client.');
        }

        return $configuration;
    }

    /** @param array<int,string> $allowedOrigins */
    private function assertAllowedReturnUrl(string $returnTo, array $allowedOrigins): void
    {
        $parts = parse_url($returnTo);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Invalid SSO return URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $origin = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        foreach ($allowedOrigins as $allowed) {
            $pattern = strtolower(rtrim(trim((string) $allowed), '/'));
            if ($pattern === '') {
                continue;
            }

            // 精确 origin 匹配
            if ($pattern === $origin) {
                return;
            }

            // 通配：https://*.chromiumapp.org
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (preg_match($regex, $origin) === 1) {
                    return;
                }
            }

            // chrome-extension://* / moz-extension://*
            if (
                ($pattern === 'chrome-extension://*' && $scheme === 'chrome-extension') ||
                ($pattern === 'moz-extension://*' && $scheme === 'moz-extension')
            ) {
                return;
            }
        }

        throw new InvalidArgumentException('SSO return URL is not allowed.');
    }

    private function ticketKey(string $ticket): string
    {
        return self::KEY_PREFIX . hash('sha256', $ticket);
    }

    private function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function consume(string $key): mixed
    {
        $connection = Redis::connection();

        try {
            return $connection->command('getdel', [$key]);
        } catch (Throwable) {
            return $connection->command('eval', [
                'local value = redis.call("GET", KEYS[1]); if value then redis.call("DEL", KEYS[1]); end; return value',
                [$key],
                1,
            ]);
        }
    }
}
