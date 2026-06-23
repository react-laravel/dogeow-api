<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Services\Cache\CacheService;
use App\Services\Web\WebPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class TitleControllerTest extends TestCase
{
    use RefreshDatabase;

    private $webPageService;

    private $cacheService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webPageService = Mockery::mock(WebPageService::class);

        $this->cacheService = Mockery::mock(CacheService::class);

        $this->app->instance(WebPageService::class, $this->webPageService);
        $this->app->instance(CacheService::class, $this->cacheService);

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetch_returns_cached_data_when_available()
    {
        $url = 'https://example.com';
        $cachedData = [
            'title' => 'Example Domain',
            'description' => 'This domain is for use in illustrative examples',
            'url' => $url,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedData);

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(200)
            ->assertJson($cachedData);
    }

    public function test_fetch_returns_cached_error_when_available()
    {
        $url = 'https://invalid-url.com';
        $cachedError = [
            'error' => '无法获取网页内容',
            'details' => 'Connection timeout',
            'status_code' => 500,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedError);

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson($cachedError);
    }

    public function test_fetch_fetches_new_data_when_not_cached()
    {
        $url = 'https://example.com';
        $fetchedData = [
            'title' => 'Example Domain',
            'description' => 'This domain is for use in illustrative examples',
            'url' => $url,
        ];

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

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }

    public function test_fetch_handles_service_exception()
    {
        $url = 'https://error-example.com';
        $exception = new \Exception('Network error');

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
                       $errorData['details'] === '无法获取目标网页内容' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson([
                'error' => '请求异常',
                'details' => '无法获取目标网页内容',
                'status_code' => 500,
            ]);
    }

    public function test_fetch_returns_400_when_url_missing()
    {
        $response = $this->getJson('/api/fetch-title');

        $response->assertStatus(400)
            ->assertJson(['error' => '缺少 url 参数']);
    }

    public function test_fetch_returns_400_when_url_empty()
    {
        $response = $this->getJson('/api/fetch-title?url=');

        $response->assertStatus(400)
            ->assertJson(['error' => '缺少 url 参数']);
    }

    public function test_fetch_with_url_encoding()
    {
        $url = 'https://example.com/path with spaces';
        $encodedUrl = urlencode($url);
        $fetchedData = [
            'title' => 'Example Page',
            'description' => 'A page with spaces in URL',
            'url' => $url,
        ];

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

        $response = $this->getJson("/api/fetch-title?url={$encodedUrl}");

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }

    // Additional test cases for better coverage

    public function test_fetch_handles_runtime_exception()
    {
        $url = 'https://runtime-error.com';
        $exception = new \RuntimeException('HTTP 404 Not Found');

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
                       $errorData['details'] === '无法获取目标网页内容' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson([
                'error' => '请求异常',
                'details' => '无法获取目标网页内容',
                'status_code' => 500,
            ]);
    }

    public function test_fetch_handles_malformed_url()
    {
        $url = 'not-a-valid-url';

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow(new \Exception('Invalid URL format'));

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === '无法获取目标网页内容' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson([
                'error' => '请求异常',
                'details' => '无法获取目标网页内容',
                'status_code' => 500,
            ]);
    }

    public function test_fetch_handles_cached_error_without_status_code()
    {
        $url = 'https://error-without-status.com';
        $cachedError = [
            'error' => '无法获取网页内容',
            'details' => 'Connection timeout',
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedError);

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson($cachedError);
    }

    public function test_fetch_handles_cached_error_with_custom_status_code()
    {
        $url = 'https://error-with-custom-status.com';
        $cachedError = [
            'error' => '无法获取网页内容',
            'details' => 'Page not found',
            'status_code' => 404,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedError);

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(404)
            ->assertJson($cachedError);
    }

    public function test_fetch_with_special_characters_in_url()
    {
        $url = 'https://example.com/path?param=value&another=param';
        $fetchedData = [
            'title' => 'Example Page with Query',
            'favicon' => 'https://example.com/favicon.ico',
        ];

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

        $response = $this->getJson('/api/fetch-title?url=' . urlencode($url));

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }

    public function test_fetch_with_very_long_url()
    {
        $url = 'https://example.com/' . str_repeat('very-long-path/', 50) . 'end';
        $fetchedData = [
            'title' => 'Long URL Page',
            'favicon' => 'https://example.com/favicon.ico',
        ];

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

        $response = $this->getJson('/api/fetch-title?url=' . urlencode($url));

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }

    public function test_fetch_with_http_url()
    {
        $url = 'http://example.com';
        $fetchedData = [
            'title' => 'HTTP Example',
            'favicon' => 'http://example.com/favicon.ico',
        ];

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

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }

    public function test_fetch_rejects_private_ip_url()
    {
        $url = 'http://127.0.0.1/admin';

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow(new \InvalidArgumentException('不允许访问内网地址'));

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === '无法获取目标网页内容' &&
                       $errorData['status_code'] === 400;
            }))
            ->once();

        $response = $this->getJson('/api/fetch-title?url=' . urlencode($url));

        $response->assertStatus(400)
            ->assertJson([
                'error' => '请求异常',
            ]);
    }

    public function test_fetch_rejects_metadata_endpoint_url()
    {
        $url = 'http://169.254.169.254/latest/meta-data/';

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow(new \InvalidArgumentException('不允许访问内网地址'));

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === '无法获取目标网页内容' &&
                       $errorData['status_code'] === 400;
            }))
            ->once();

        $response = $this->getJson('/api/fetch-title?url=' . urlencode($url));

        $response->assertStatus(400)
            ->assertJson([
                'error' => '请求异常',
            ]);
    }
}
