<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\TitleController;
use App\Services\Cache\CacheService;
use App\Services\Web\WebPageService;
use Illuminate\Http\Request;
use Tests\TestCase;

class TitleControllerUnitTest extends TestCase
{
    private TitleController $controller;

    private WebPageService $webPageService;

    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webPageService = new WebPageService;
        $this->cacheService = new CacheService;
        $this->controller = new TitleController($this->webPageService, $this->cacheService);
    }

    public function test_fetch_returns_error_when_url_missing(): void
    {
        $request = new Request;

        $response = $this->controller->fetch($request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('缺少url参数', $data['error']);
    }

    public function test_fetch_returns_cached_data(): void
    {
        $this->cacheService->putSuccess('https://example.com', ['title' => 'Cached Title']);

        $request = new Request;
        $request->query->set('url', 'https://example.com');

        $response = $this->controller->fetch($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
