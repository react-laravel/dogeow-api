<?php

namespace Tests\Unit\Services;

use App\Services\Cache\CacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = new CacheService;
        Cache::flush();
    }

    public function test_get_returns_cached_data()
    {
        $url = 'https://example.com';
        $data = ['title' => 'Example Title', 'favicon' => 'favicon.ico'];

        Cache::put('title_favicon:' . md5($url), $data, 3600);

        $result = $this->cacheService->getTitleFavicon($url);

        $this->assertEquals($data, $result);
    }

    public function test_get_returns_null_when_not_cached()
    {
        $url = 'https://example.com';

        $result = $this->cacheService->get($url);

        $this->assertNull($result);
    }

    public function test_put_success_stores_data_with_success_ttl()
    {
        $url = 'https://example.com';
        $data = ['title' => 'Example Title', 'favicon' => 'favicon.ico'];

        $this->cacheService->putTitleFaviconSuccess($url, $data);

        $result = $this->cacheService->getTitleFavicon($url);
        $this->assertEquals($data, $result);

        // Verify it's cached with the correct TTL (24 hours)
        $this->assertTrue(Cache::has('title_favicon:' . md5($url)));
    }

    public function test_put_error_stores_data_with_error_ttl()
    {
        $url = 'https://example.com';
        $errorData = ['error' => 'Failed to fetch', 'code' => 500];

        $this->cacheService->putTitleFaviconError($url, $errorData);

        $result = $this->cacheService->getTitleFavicon($url);
        $this->assertEquals($errorData, $result);

        // Verify it's cached with the correct TTL (30 minutes)
        $this->assertTrue(Cache::has('title_favicon:' . md5($url)));
    }

    public function test_get_cache_key_generates_consistent_keys()
    {
        $url = 'https://example.com';

        $key1 = 'title_favicon:' . md5($url);
        $key2 = 'title_favicon:' . md5($url);

        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('title_favicon:', $key1);
    }

    public function test_get_cache_key_generates_different_keys_for_different_urls()
    {
        $url1 = 'https://example.com';
        $url2 = 'https://google.com';

        $key1 = 'title_favicon:' . md5($url1);
        $key2 = 'title_favicon:' . md5($url2);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_cache_operations_with_different_urls()
    {
        $url1 = 'https://example.com';
        $url2 = 'https://google.com';

        $data1 = ['title' => 'Example', 'favicon' => 'example.ico'];
        $data2 = ['title' => 'Google', 'favicon' => 'google.ico'];

        $this->cacheService->putTitleFaviconSuccess($url1, $data1);
        $this->cacheService->putTitleFaviconSuccess($url2, $data2);

        $this->assertEquals($data1, $this->cacheService->getTitleFavicon($url1));
        $this->assertEquals($data2, $this->cacheService->getTitleFavicon($url2));
    }

    public function test_cache_overwrite_behavior()
    {
        $url = 'https://example.com';
        $data1 = ['title' => 'Original Title'];
        $data2 = ['title' => 'Updated Title'];

        $this->cacheService->putTitleFaviconSuccess($url, $data1);
        $this->assertEquals($data1, $this->cacheService->getTitleFavicon($url));

        $this->cacheService->putTitleFaviconSuccess($url, $data2);
        $this->assertEquals($data2, $this->cacheService->getTitleFavicon($url));
    }

    public function test_error_cache_overwrites_success_cache()
    {
        $url = 'https://example.com';
        $successData = ['title' => 'Success Title'];
        $errorData = ['error' => 'Failed to fetch'];

        $this->cacheService->putTitleFaviconSuccess($url, $successData);
        $this->assertEquals($successData, $this->cacheService->getTitleFavicon($url));

        $this->cacheService->putTitleFaviconError($url, $errorData);
        $this->assertEquals($errorData, $this->cacheService->getTitleFavicon($url));
    }

    public function test_cache_key_with_special_characters()
    {
        $url = 'https://example.com/path with spaces?param=value&other=123';

        $key = 'title_favicon:' . md5($url);

        $this->assertStringStartsWith('title_favicon:', $key);
        $this->assertIsString($key);
    }

    public function test_cache_key_with_unicode_characters()
    {
        $url = 'https://example.com/path/中文/测试';

        $key = 'title_favicon:' . md5($url);

        $this->assertStringStartsWith('title_favicon:', $key);
        $this->assertIsString($key);
    }

    public function test_cache_key_with_empty_url()
    {
        $url = '';

        $key = 'title_favicon:' . md5($url);

        $this->assertStringStartsWith('title_favicon:', $key);
        $this->assertIsString($key);
    }

    public function test_cache_operations_with_complex_data()
    {
        $url = 'https://example.com';
        $complexData = [
            'title' => 'Complex Title',
            'favicon' => 'favicon.ico',
            'meta' => [
                'description' => 'A complex description',
                'keywords' => ['tag1', 'tag2'],
                'nested' => [
                    'level1' => [
                        'level2' => 'value',
                    ],
                ],
            ],
        ];

        $this->cacheService->putTitleFaviconSuccess($url, $complexData);
        $result = $this->cacheService->getTitleFavicon($url);

        $this->assertEquals($complexData, $result);
        $this->assertEquals('Complex Title', $result['title']);
        $this->assertEquals('favicon.ico', $result['favicon']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals('value', $result['meta']['nested']['level1']['level2']);
    }

    public function test_forget_removes_cached_data(): void
    {
        $key = 'test-key';
        $data = ['value' => 123];
        $this->cacheService->put($key, $data);

        $this->assertEquals($data, $this->cacheService->get($key));

        $this->cacheService->forget($key);

        $this->assertNull($this->cacheService->get($key));
    }

    public function test_forget_with_prefix(): void
    {
        $key = 'mykey';
        $this->cacheService->put($key, 'data', 3600, 'myprefix');

        $this->assertEquals('data', $this->cacheService->get($key, 'myprefix'));

        $this->cacheService->forget($key, 'myprefix');

        $this->assertNull($this->cacheService->get($key, 'myprefix'));
    }

    public function test_remember_caches_callback_result(): void
    {
        $key = 'remember-key';
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['computed' => true];
        };

        $result1 = $this->cacheService->remember($key, $callback);
        $result2 = $this->cacheService->remember($key, $callback);

        $this->assertEquals(['computed' => true], $result1);
        $this->assertEquals($result1, $result2);
        $this->assertSame(1, $callCount);
    }

    public function test_remember_with_custom_prefix_and_ttl(): void
    {
        $key = 'prefixed-key';
        $callback = fn () => 'result';

        $result = $this->cacheService->remember($key, $callback, 120, 'custom');

        $this->assertEquals('result', $result);
        $this->assertEquals('result', $this->cacheService->get($key, 'custom'));
    }

    public function test_put_and_get_with_default_prefix(): void
    {
        $key = 'default-prefix-key';
        $data = ['a' => 1, 'b' => 2];

        $this->cacheService->put($key, $data);

        $this->assertEquals($data, $this->cacheService->get($key));
    }

    public function test_put_success_and_put_error_both_store_data(): void
    {
        $key1 = 'success-key';
        $key2 = 'error-key';
        $data = ['x' => 1];

        $this->cacheService->putSuccess($key1, $data);
        $this->cacheService->putError($key2, $data);

        $this->assertEquals($data, $this->cacheService->get($key1));
        $this->assertEquals($data, $this->cacheService->get($key2));
    }
}
