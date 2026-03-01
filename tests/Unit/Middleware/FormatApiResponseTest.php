<?php

namespace Tests\Unit\Middleware\FormatApiResponse;

use App\Http\Middleware\FormatApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FormatApiResponseTest extends TestCase
{
    private FormatApiResponse $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new FormatApiResponse;
    }

    public function test_handle_returns_response_for_non_api_routes(): void
    {
        $request = new Request;
        $request->setMethod('GET');

        $response = new Response('test');

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertInstanceOf(Response::class, $result);
    }

    public function test_handle_formats_json_response_for_api_routes(): void
    {
        $request = new Request;
        $request->setMethod('GET');
        $request->headers->set('Accept', 'application/json');
        $request->server->set('REQUEST_URI', '/api/test');

        $response = new JsonResponse(['data' => 'test']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertInstanceOf(JsonResponse::class, $result);
        $data = $result->getData(true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_handle_does_not_format_already_formatted_response(): void
    {
        $request = new Request;
        $request->setMethod('GET');

        $response = new JsonResponse(['success' => true, 'data' => 'test']);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $this->assertSame($response, $result);
    }
}
