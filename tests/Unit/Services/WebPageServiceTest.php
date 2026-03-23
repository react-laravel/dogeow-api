<?php

namespace Tests\Unit\Services;

use App\Services\Web\WebPageService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebPageServiceTest extends TestCase
{
    private WebPageService $webPageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webPageService = new WebPageService;
    }

    public function test_fetch_content_returns_title_and_favicon()
    {
        $html = '<html><head><title>Test Page</title><link rel="icon" href="/favicon.ico"></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('favicon', $result);
        $this->assertEquals('Test Page', $result['title']);
        $this->assertEquals('https://example.com/favicon.ico', $result['favicon']);
    }

    public function test_fetch_content_throws_exception_for_failed_request()
    {
        Http::fake([
            'https://example.com' => Http::response('', 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('获取网页失败: 404');

        $this->webPageService->fetchContent('https://example.com');
    }

    public function test_fetch_content_handles_missing_title()
    {
        $html = '<html><head></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertEquals('', $result['title']);
        $this->assertEquals('https://example.com/favicon.ico', $result['favicon']);
    }

    public function test_fetch_content_handles_absolute_favicon_url()
    {
        $html = '<html><head><title>Test Page</title><link rel="icon" href="https://example.com/custom.ico"></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertEquals('https://example.com/custom.ico', $result['favicon']);
    }

    public function test_fetch_content_handles_relative_favicon_url()
    {
        $html = '<html><head><title>Test Page</title><link rel="icon" href="/images/favicon.ico"></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertEquals('https://example.com/images/favicon.ico', $result['favicon']);
    }

    public function test_fetch_content_handles_relative_favicon_url_with_path()
    {
        $html = '<html><head><title>Test Page</title><link rel="icon" href="favicon.ico"></head><body>Content</body></html>';

        Http::fake([
            'https://example.com/page' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com/page');

        $this->assertEquals('https://example.com/favicon.ico', $result['favicon']);
    }

    public function test_fetch_content_uses_default_favicon_when_not_found()
    {
        $html = '<html><head><title>Test Page</title></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertEquals('https://example.com/favicon.ico', $result['favicon']);
    }

    public function test_fetch_content_handles_shortcut_icon_rel()
    {
        $html = '<html><head><title>Test Page</title><link rel="shortcut icon" href="/favicon.ico"></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertEquals('https://example.com/favicon.ico', $result['favicon']);
    }

    public function test_fetch_content_adds_https_to_url_without_protocol()
    {
        $html = '<html><head><title>Test Page</title></head><body>Content</body></html>';

        Http::fake([
            'https://example.com*' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('example.com');

        $this->assertEquals('Test Page', $result['title']);
    }

    public function test_fetch_content_handles_http_protocol()
    {
        $html = '<html><head><title>HTTP Page</title></head><body>Content</body></html>';

        Http::fake([
            'http://example.com*' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('http://example.com');

        $this->assertEquals('HTTP Page', $result['title']);
    }

    public function test_fetch_content_handles_protocol_relative_favicon()
    {
        $html = '<html><head><title>Test Page</title><link rel="icon" href="//cdn.example.com/favicon.ico"></head><body>Content</body></html>';

        Http::fake([
            'https://example.com' => Http::response($html, 200),
        ]);

        $result = $this->webPageService->fetchContent('https://example.com');

        $this->assertEquals('https://cdn.example.com/favicon.ico', $result['favicon']);
    }
}
