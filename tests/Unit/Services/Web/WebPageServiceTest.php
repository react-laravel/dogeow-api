<?php

namespace Tests\Unit\Services\Web;

use App\Services\Web\WebPageService;
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
        // TODO: Implement test
    }

    public function test_fetch_content_normalizes_http_url(): void
    {
        // TODO: Implement test
    }

    public function test_fetch_content_throws_exception_on_failed_request(): void
    {
        // TODO: Implement test
    }

    public function test_fetch_content_handles_https_url(): void
    {
        // TODO: Implement test
    }
}
