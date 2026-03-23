<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RateLimitMiddleware;
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('rate_limit:ip:127.0.0.1');
        parent::tearDown();
    }

    public function test_handle_allows_request_when_under_limit(): void
    {
        RateLimiter::clear('rate_limit:ip:127.0.0.1');

        $request = new Request;
        $request->setMethod('GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next');
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('60', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_handle_returns_429_when_rate_limit_exceeded(): void
    {
        $key = 'rate_limit:ip:127.0.0.1';
        RateLimiter::clear($key);
        RateLimiter::hit($key, 60);

        $request = new Request;
        $request->setMethod('GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next');
        }, '1', '1');

        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('retry_after', $data);
        $this->assertEquals('请求过于频繁，请稍后再试', $data['message']);
    }

    public function test_handle_uses_user_identifier_when_authenticated(): void
    {
        RateLimiter::clear('rate_limit:user:1');

        $request = new Request;
        $request->setMethod('GET');

        $user = new class
        {
            public function getAuthIdentifier(): int
            {
                return 1;
            }
        };

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next');
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_resolve_request_signature_uses_user_id_when_authenticated(): void
    {
        $request = new Request;

        $user = new class
        {
            public function getAuthIdentifier(): int
            {
                return 42;
            }
        };

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $signature = $method->invoke($this->middleware, $request);

        $this->assertEquals('rate_limit:user:42', $signature);
    }

    public function test_resolve_request_signature_uses_ip_when_not_authenticated(): void
    {
        $request = new Request;
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $reflection = new \ReflectionClass($this->middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $signature = $method->invoke($this->middleware, $request);

        $this->assertEquals('rate_limit:ip:192.168.1.100', $signature);
    }
}
