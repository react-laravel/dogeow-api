<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\TitleController;
use App\Services\Cache\CacheService;
use App\Services\Web\WebPageService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class TitleControllerTest extends TestCase
{
    private $webPageService;

    private $cacheService;

    private $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webPageService = Mockery::mock(WebPageService::class);
        $this->cacheService = Mockery::mock(CacheService::class);
        $this->controller = new TitleController($this->webPageService, $this->cacheService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetch_returns_cached_data()
    {
        $url = 'https://example.com';
        $cachedData = [
            'title' => 'Example Domain',
            'favicon' => 'https://example.com/favicon.ico',
        ];

        $request = new Request(['url' => $url]);

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedData);

        $response = $this->controller->fetch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($cachedData, json_decode($response->getContent(), true));
    }

    public function test_fetch_returns_cached_error()
    {
        $url = 'https://error-example.com';
        $cachedError = [
            'error' => '无法获取网页内容',
            'details' => 'Connection timeout',
            'status_code' => 500,
        ];

        $request = new Request(['url' => $url]);

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedError);

        $response = $this->controller->fetch($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($cachedError, json_decode($response->getContent(), true));
    }

    public function test_fetch_fetches_new_data_and_caches()
    {
        $url = 'https://example.com';
        $fetchedData = [
            'title' => 'Example Domain',
            'favicon' => 'https://example.com/favicon.ico',
        ];

        $request = new Request(['url' => $url]);

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andReturn($fetchedData);

        $this->cacheService->shouldReceive('putSuccess')
            ->with($url, $fetchedData)
            ->once();

        $response = $this->controller->fetch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($fetchedData, json_decode($response->getContent(), true));
    }

    public function test_fetch_handles_exception_and_caches_error()
    {
        $url = 'https://error-example.com';
        $exception = new \Exception('Network error');

        $request = new Request(['url' => $url]);

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow($exception);

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === 'Network error' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->controller->fetch($request);

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('请求异常', $responseData['error']);
        $this->assertEquals('Network error', $responseData['details']);
        $this->assertEquals(500, $responseData['status_code']);
    }

    public function test_fetch_returns_400_when_url_missing()
    {
        $request = new Request;

        $response = $this->controller->fetch($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(['error' => '缺少 url 参数'], json_decode($response->getContent(), true));
    }

    public function test_fetch_returns_400_when_url_empty()
    {
        $request = new Request(['url' => '']);

        $response = $this->controller->fetch($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(['error' => '缺少 url 参数'], json_decode($response->getContent(), true));
    }

    public function test_fetch_returns_400_when_url_null()
    {
        $request = new Request(['url' => null]);

        $response = $this->controller->fetch($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(['error' => '缺少 url 参数'], json_decode($response->getContent(), true));
    }

    public function test_fetch_with_runtime_exception()
    {
        $url = 'https://runtime-error.com';
        $exception = new \RuntimeException('HTTP 404 Not Found');

        $request = new Request(['url' => $url]);

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow($exception);

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === 'HTTP 404 Not Found' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->controller->fetch($request);

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('请求异常', $responseData['error']);
        $this->assertEquals('HTTP 404 Not Found', $responseData['details']);
        $this->assertEquals(500, $responseData['status_code']);
    }

    public function test_fetch_with_custom_exception()
    {
        $url = 'https://custom-error.com';
        $exception = new \InvalidArgumentException('Invalid URL format');

        $request = new Request(['url' => $url]);

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow($exception);

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === 'Invalid URL format' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->controller->fetch($request);

        $this->assertEquals(500, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('请求异常', $responseData['error']);
        $this->assertEquals('Invalid URL format', $responseData['details']);
        $this->assertEquals(500, $responseData['status_code']);
    }
}
