<?php

namespace Tests\Unit\Services;

use App\Services\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class CacheServiceRedisTest extends TestCase
{
    use RefreshDatabase;

    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we exercise the Redis-specific code paths
        Config::set('cache.default', 'redis');

        $this->cacheService = new CacheService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_forget_by_prefix_deletes_matching_keys_from_redis()
    {
        $prefix = 'title_favicon';

        // buildCacheKey('*', $prefix) -> "{$prefix}:" . md5('*')
        $expectedPattern = $prefix . ':' . md5('*');

        // Prepare mock Redis connection
        $redisMock = Mockery::mock();

        // Expect keys call and return two keys that should be deleted
        $redisMock->shouldReceive('keys')
            ->once()
            ->with($expectedPattern)
            ->andReturn([
                "{$expectedPattern}:k1",
                "{$expectedPattern}:k2",
            ]);

        // Expect del to be called with the keys returned above
        $redisMock->shouldReceive('del')
            ->once()
            ->with([
                "{$expectedPattern}:k1",
                "{$expectedPattern}:k2",
            ])->andReturn(2);

        // When the Redis facade requests a connection, return our mock
        Redis::shouldReceive('connection')->andReturn($redisMock);

        // Call the method under test
        $this->cacheService->forgetByPrefix($prefix);

        // Basic assertion to mark the test as having an assertion (mock expectations validate behavior)
        $this->assertTrue(true);
    }

    public function test_forget_by_prefix_no_op_when_no_keys_found()
    {
        $prefix = 'some_other_prefix';
        $expectedPattern = $prefix . ':' . md5('*');

        $redisMock = Mockery::mock();

        // keys returns empty array -> no del call should be made
        $redisMock->shouldReceive('keys')
            ->once()
            ->with($expectedPattern)
            ->andReturn([]);

        // Ensure del is not called
        $redisMock->shouldNotReceive('del');

        Redis::shouldReceive('connection')->andReturn($redisMock);

        // Should not throw and should be a no-op
        $this->cacheService->forgetByPrefix($prefix);

        $this->assertTrue(true);
    }
}
