<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CombatRateLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CombatRateLimitTest extends TestCase
{
    private CombatRateLimit $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CombatRateLimit;
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('combat:1:1');
        RateLimiter::clear('combat:1:2');
        parent::tearDown();
    }

    public function test_handle_passes_without_character_id(): void
    {
        $request = new Request;
        $request->setMethod('GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next');
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_handle_passes_without_user(): void
    {
        $request = new Request;
        $request->setMethod('GET');
        $request->merge(['character_id' => 1]);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next');
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_handle_allows_first_request(): void
    {
        RateLimiter::clear('combat:1:1');

        $request = new Request;
        $request->setMethod('GET');
        $request->merge(['character_id' => 1]);

        $user = new class
        {
            public $id = 1;

            public function getAuthIdentifier(): int
            {
                return $this->id;
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

    public function test_handle_returns_429_when_rate_limited(): void
    {
        $key = 'combat:1:2';
        RateLimiter::clear($key);
        RateLimiter::hit($key, 4);

        $request = new Request;
        $request->setMethod('GET');
        $request->merge(['character_id' => 2]);

        $user = new class
        {
            public $id = 1;

            public function getAuthIdentifier(): int
            {
                return $this->id;
            }
        };

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next');
        });

        $this->assertEquals(429, $response->getStatusCode());
    }
}
