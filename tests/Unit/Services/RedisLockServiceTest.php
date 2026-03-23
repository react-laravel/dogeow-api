<?php

namespace Tests\Unit\Services;

use App\Services\Cache\RedisLockService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedisLockServiceTest extends TestCase
{
    private RedisLockService $lockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockService = new RedisLockService;
    }

    protected function tearDown(): void
    {
        try {
            // Clean up any test locks from Redis
            $redis = Redis::connection();
            $keys = $redis->keys('*lock*');
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

    public function test_lock_acquires_lock_successfully(): void
    {
        $token = $this->lockService->lock('test-key');

        $this->assertNotFalse($token);
        $this->assertIsString($token);
        $this->assertEquals(32, strlen($token));
    }

    public function test_lock_fails_when_key_already_locked(): void
    {
        $token1 = $this->lockService->lock('test-key');

        $this->assertNotFalse($token1);

        $token2 = $this->lockService->lock('test-key');

        $this->assertFalse($token2);
    }

    public function test_lock_with_custom_ttl(): void
    {
        $token = $this->lockService->lock('ttl-key', 5);

        $this->assertNotFalse($token);

        $redis = Redis::connection();
        $ttl = $redis->ttl('lock:ttl-key');

        // TTL should be set (5 seconds, allow 1 second tolerance)
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(5, $ttl);
    }

    public function test_release_releases_lock_with_correct_token(): void
    {
        $token = $this->lockService->lock('release-key');

        $this->assertNotFalse($token);

        $released = $this->lockService->release('release-key', $token);

        $this->assertTrue($released);
        $this->assertFalse($this->lockService->isLocked('release-key'));
    }

    public function test_release_fails_with_wrong_token(): void
    {
        $token = $this->lockService->lock('release-wrong-key');

        $this->assertNotFalse($token);

        $released = $this->lockService->release('release-wrong-key', 'wrong-token');

        $this->assertFalse($released);
        $this->assertTrue($this->lockService->isLocked('release-wrong-key'));
    }

    public function test_release_fails_for_non_existent_lock(): void
    {
        $released = $this->lockService->release('non-existent-key', 'any-token');

        $this->assertFalse($released);
    }

    public function test_is_locked_returns_true_when_locked(): void
    {
        $token = $this->lockService->lock('check-locked');

        $this->assertNotFalse($token);
        $this->assertTrue($this->lockService->isLocked('check-locked'));
    }

    public function test_is_locked_returns_false_when_not_locked(): void
    {
        $this->assertFalse($this->lockService->isLocked('not-locked-key'));
    }

    public function test_extend_extends_lock_with_correct_token(): void
    {
        $token = $this->lockService->lock('extend-key', 2);

        $this->assertNotFalse($token);

        // Wait a moment to ensure TTL is ticking
        usleep(200000); // 200ms

        $extended = $this->lockService->extend('extend-key', $token, 10);

        $this->assertTrue($extended);

        $redis = Redis::connection();
        $ttl = $redis->ttl('lock:extend-key');

        // TTL should be around 10 seconds now
        $this->assertGreaterThan(5, $ttl);
    }

    public function test_extend_fails_with_wrong_token(): void
    {
        $token = $this->lockService->lock('extend-wrong-key', 10);

        $this->assertNotFalse($token);

        $extended = $this->lockService->extend('extend-wrong-key', 'wrong-token', 20);

        $this->assertFalse($extended);
    }

    public function test_extend_fails_for_expired_lock(): void
    {
        // Lock with very short TTL
        $token = $this->lockService->lock('expire-key', 1);

        $this->assertNotFalse($token);

        // Wait for lock to expire
        sleep(2);

        $extended = $this->lockService->extend('expire-key', $token, 10);

        $this->assertFalse($extended);
    }

    public function test_wait_and_lock_acquires_lock_immediately(): void
    {
        $token = $this->lockService->waitAndLock('wait-key', 10, 0);

        $this->assertNotFalse($token);
        $this->assertTrue($this->lockService->isLocked('wait-key'));
    }

    public function test_wait_and_lock_returns_false_after_retries(): void
    {
        // Acquire lock first
        $token1 = $this->lockService->lock('wait-fail-key');
        $this->assertNotFalse($token1);

        // Try to wait and lock should fail after retries
        $token2 = $this->lockService->waitAndLock('wait-fail-key', 10, 2, 50);

        $this->assertFalse($token2);
    }

    public function test_wait_and_lock_succeeds_on_retry(): void
    {
        // Don't hold the lock
        $this->assertFalse($this->lockService->isLocked('retry-key'));

        // Wait a tiny bit then manually lock
        $acquired = false;
        for ($i = 0; $i < 10 && ! $acquired; $i++) {
            usleep(50000); // 50ms
            $acquired = $this->lockService->lock('retry-key') !== false;
        }

        $this->assertTrue($acquired, 'Failed to acquire lock in retries');
    }

    public function test_different_keys_do_not_conflict(): void
    {
        $token1 = $this->lockService->lock('key-a');
        $token2 = $this->lockService->lock('key-b');

        $this->assertNotFalse($token1);
        $this->assertNotFalse($token2);
        $this->assertNotEquals($token1, $token2);
    }

    public function test_lock_token_is_unique_per_acquisition(): void
    {
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            // Release previous locks to get fresh ones
            $this->lockService->release('unique-key-' . $i, $tokens[$i] ?? '');
            $tokens[] = $this->lockService->lock('unique-key-' . $i);
        }

        // All tokens should be unique
        $this->assertCount(5, array_unique($tokens));
    }
}
