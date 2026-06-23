<?php

namespace Tests\Unit\Services\Web;

use App\Services\Web\WebPageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebPageServiceTest extends TestCase
{
    protected WebPageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebPageService;
    }

    public function test_fetch_content_returns_title_and_favicon(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('<html><head><title>Test Page</title></head><body></body></html>', 200),
        ]);

        $result = $this->service->fetchContent('https://example.com/test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('favicon', $result);
        $this->assertSame('Test Page', $result['title']);
    }

    public function test_fetch_content_normalizes_http_url(): void
    {
        Http::fake([
            'http://example.com/*' => Http::response('<html><head><title>Test</title></head><body></body></html>', 200),
        ]);

        $result = $this->service->fetchContent('http://example.com/test');

        $this->assertIsArray($result);
    }

    public function test_fetch_content_throws_exception_on_failed_request(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/获取网页失败/');

        $this->service->fetchContent('https://example.com/fail');
    }

    public function test_fetch_content_handles_https_url(): void
    {
        Http::fake([
            'https://secure.example.com/*' => Http::response('<html><head><title>Secure Page</title></head><body></body></html>', 200),
        ]);

        $result = $this->service->fetchContent('https://secure.example.com/page');

        $this->assertSame('Secure Page', $result['title']);
    }

    public function test_fetch_content_throws_exception_on_404(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('获取网页失败: 404');

        $this->service->fetchContent('https://example.com/not-found');
    }

    public function test_fetch_content_throws_exception_on_timeout(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection timed out');

        $this->service->fetchContent('https://example.com/slow');
    }

    public function test_fetch_content_rejects_localhost_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/无法获取目标网页内容/');

        $this->service->fetchContent('http://127.0.0.1/admin');
    }

    public function test_fetch_content_rejects_metadata_endpoint_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/无法获取目标网页内容/');

        $this->service->fetchContent('http://169.254.169.254/latest/meta-data/');
    }

    public function test_fetch_content_rejects_file_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/仅支持 HTTP\/HTTPS 协议/');

        $this->service->fetchContent('file:///etc/passwd');
    }

    public function test_fetch_content_rejects_gopher_scheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/仅支持 HTTP\/HTTPS 协议/');

        $this->service->fetchContent('gopher://evil.com/');
    }
}
