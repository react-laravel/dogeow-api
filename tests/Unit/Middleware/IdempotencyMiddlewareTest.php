<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    private IdempotencyMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new IdempotencyMiddleware;
    }

    protected function tearDown(): void
    {
        try {
            // Clean up any test keys from Redis
            $redis = Redis::connection();
            $keys = $redis->keys('*idempotency*');
            if (! empty($keys)) {
                // Strip Laravel's Redis prefix before deleting
                $prefix = config('database.redis.options.prefix', 'laravel_database_');
                $keysToDelete = array_map(fn ($key) => Str::replaceFirst($prefix, '', $key), $keys);
                $redis->del($keysToDelete);
            }
        } catch (\Throwable $e) {
            // Ignore cleanup errors to avoid masking test failures
        } finally {
            parent::tearDown();
        }
    }

    public function test_passes_through_get_requests_without_idempotency_key(): void
    {
        $request = new Request;
        $request->setMethod('GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Idempotent-Replay'));
    }

    public function test_passes_through_post_requests_without_idempotency_key(): void
    {
        $request = new Request;
        $request->setMethod('POST');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next', 201);
        });

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Idempotent-Replay'));
    }

    public function test_returns_400_for_invalid_idempotency_key_too_long(): void
    {
        $request = new Request;
        $request->setMethod('POST');
        $request->headers->set('X-Idempotency-Key', str_repeat('a', 129));

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next', 201);
        });

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid X-Idempotency-Key', $responseData['message']);
    }

    public function test_returns_400_for_invalid_idempotency_key_special_chars(): void
    {
        $request = new Request;
        $request->setMethod('POST');
        $request->headers->set('X-Idempotency-Key', 'key with spaces!@#');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('next', 201);
        });

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_passes_through_with_valid_idempotency_key_first_request(): void
    {
        $request = new Request;
        $request->setMethod('POST');
        $request->headers->set('X-Idempotency-Key', 'test-key-123');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('created', 201);
        });

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('created', $response->getContent());
        $this->assertFalse($response->headers->has('X-Idempotent-Replay'));
    }

    public function test_returns_cached_response_for_duplicate_post_with_same_key(): void
    {
        $request1 = new Request;
        $request1->setMethod('POST');
        $request1->headers->set('X-Idempotency-Key', 'duplicate-key-456');
        $request1->server->set('REQUEST_URI', '/api/test');

        $response1 = $this->middleware->handle($request1, function ($req) {
            return new Response('original-response', 201);
        });

        $this->assertEquals(201, $response1->getStatusCode());

        // Second request with same key should return cached response
        $request2 = new Request;
        $request2->setMethod('POST');
        $request2->headers->set('X-Idempotency-Key', 'duplicate-key-456');
        $request2->server->set('REQUEST_URI', '/api/test');

        $response2 = $this->middleware->handle($request2, function ($req) {
            return new Response('should-not-be-seen', 201);
        });

        $this->assertEquals(201, $response2->getStatusCode());
        $this->assertEquals('original-response', $response2->getContent());
        $this->assertTrue($response2->headers->has('X-Idempotent-Replay'));
        $this->assertEquals('true', $response2->headers->get('X-Idempotent-Replay'));
    }

    public function test_does_not_cache_failed_responses(): void
    {
        $request1 = new Request;
        $request1->setMethod('POST');
        $request1->headers->set('X-Idempotency-Key', 'fail-key-789');
        $request1->server->set('REQUEST_URI', '/api/test');

        $this->middleware->handle($request1, function ($req) {
            return new Response('error', 500);
        });

        // Second request should NOT return cached 500, should re-process
        $request2 = new Request;
        $request2->setMethod('POST');
        $request2->headers->set('X-Idempotency-Key', 'fail-key-789');
        $request2->server->set('REQUEST_URI', '/api/test');

        $reachedHandler = false;
        $this->middleware->handle($request2, function ($req) use (&$reachedHandler) {
            $reachedHandler = true;

            return new Response('retry-success', 200);
        });

        $this->assertTrue($reachedHandler, 'Handler should be reached for non-cached failed requests');
    }

    public function test_different_endpoints_do_not_share_idempotency_keys(): void
    {
        $request1 = new Request;
        $request1->setMethod('POST');
        $request1->headers->set('X-Idempotency-Key', 'same-key-different-endpoint');
        $request1->server->set('REQUEST_URI', '/api/endpoint-a');
        $request1->server->set('PATH_INFO', '/api/endpoint-a');

        $response1 = $this->middleware->handle($request1, function ($req) {
            return new Response('endpoint-a-response', 201);
        });

        $request2 = new Request;
        $request2->setMethod('POST');
        $request2->headers->set('X-Idempotency-Key', 'same-key-different-endpoint');
        $request2->server->set('REQUEST_URI', '/api/endpoint-b');
        $request2->server->set('PATH_INFO', '/api/endpoint-b');

        $response2 = $this->middleware->handle($request2, function ($req) {
            return new Response('endpoint-b-response', 201);
        });

        // Different endpoints should NOT share cache
        $this->assertEquals('endpoint-a-response', $response1->getContent());
        $this->assertEquals('endpoint-b-response', $response2->getContent());
        $this->assertFalse($response2->headers->has('X-Idempotent-Replay'));
    }

    public function test_put_requests_are_cached_by_idempotency_key(): void
    {
        $request1 = new Request;
        $request1->setMethod('PUT');
        $request1->headers->set('X-Idempotency-Key', 'put-key-123');
        $request1->server->set('REQUEST_URI', '/api/test');

        $response1 = $this->middleware->handle($request1, function ($req) {
            return new Response('updated', 200);
        });

        $this->assertEquals(200, $response1->getStatusCode());

        $request2 = new Request;
        $request2->setMethod('PUT');
        $request2->headers->set('X-Idempotency-Key', 'put-key-123');
        $request2->server->set('REQUEST_URI', '/api/test');

        $response2 = $this->middleware->handle($request2, function ($req) {
            return new Response('should-not-be-seen', 200);
        });

        $this->assertEquals('updated', $response2->getContent());
        $this->assertTrue($response2->headers->has('X-Idempotent-Replay'));
    }

    public function test_delete_requests_are_cached_by_idempotency_key(): void
    {
        $request1 = new Request;
        $request1->setMethod('DELETE');
        $request1->headers->set('X-Idempotency-Key', 'delete-key-123');
        $request1->server->set('REQUEST_URI', '/api/test');

        $response1 = $this->middleware->handle($request1, function ($req) {
            return new Response('deleted', 204);
        });

        $this->assertEquals(204, $response1->getStatusCode());

        $request2 = new Request;
        $request2->setMethod('DELETE');
        $request2->headers->set('X-Idempotency-Key', 'delete-key-123');
        $request2->server->set('REQUEST_URI', '/api/test');

        $response2 = $this->middleware->handle($request2, function ($req) {
            return new Response('should-not-be-seen', 204);
        });

        $this->assertEquals('deleted', $response2->getContent());
        $this->assertTrue($response2->headers->has('X-Idempotent-Replay'));
    }

    public function test_redirect_responses_are_cached(): void
    {
        $request1 = new Request;
        $request1->setMethod('POST');
        $request1->headers->set('X-Idempotency-Key', 'redirect-key');
        $request1->server->set('REQUEST_URI', '/api/test');
        $request1->server->set('PATH_INFO', '/api/test');

        $response1 = $this->middleware->handle($request1, function ($req) {
            $response = new Response('', 302);
            $response->headers->set('Location', '/redirected');

            return $response;
        });

        $this->assertEquals(302, $response1->getStatusCode());

        $request2 = new Request;
        $request2->setMethod('POST');
        $request2->headers->set('X-Idempotency-Key', 'redirect-key');
        $request2->server->set('REQUEST_URI', '/api/test');
        $request2->server->set('PATH_INFO', '/api/test');

        $response2 = $this->middleware->handle($request2, function ($req) {
            $response = new Response('', 302);
            $response->headers->set('Location', '/other');

            return $response;
        });

        $this->assertEquals(302, $response2->getStatusCode());
        $this->assertEquals('/redirected', $response2->headers->get('Location'));
        $this->assertTrue($response2->headers->has('X-Idempotent-Replay'));
    }
}
