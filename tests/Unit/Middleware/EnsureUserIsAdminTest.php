<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    private EnsureUserIsAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureUserIsAdmin;
    }

    public function test_handle_returns_401_when_user_not_authenticated(): void
    {
        $request = new Request;

        $response = $this->middleware->handle($request, function () {
            return new Response('next');
        });

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('未认证', $data['message']);
    }

    public function test_handle_returns_403_when_user_is_not_admin(): void
    {
        $request = new Request;

        $user = new class
        {
            public function isAdmin(): bool
            {
                return false;
            }
        };

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->middleware->handle($request, function () {
            return new Response('next');
        });

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('需要管理员权限', $data['message']);
    }

    public function test_handle_passes_when_user_is_admin(): void
    {
        $request = new Request;

        $user = new class
        {
            public function isAdmin(): bool
            {
                return true;
            }
        };

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $nextCalled = false;
        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return new Response('next');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}
