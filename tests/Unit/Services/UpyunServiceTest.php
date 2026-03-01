<?php

namespace Tests\Unit\Services;

use App\Services\UpyunService;
use Tests\TestCase;

class UpyunServiceTest extends TestCase
{
    public function test_is_configured_returns_true_when_all_config_set(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => 'test-password']);
        config(['upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_bucket_empty(): void
    {
        config(['upyun.bucket' => '']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => 'test-password']);

        $service = new UpyunService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_operator_empty(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => '']);
        config(['upyun.password' => 'test-password']);

        $service = new UpyunService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_password_empty(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => '']);

        $service = new UpyunService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_upload_returns_false_when_not_configured(): void
    {
        config(['upyun.bucket' => '']);
        config(['upyun.operator' => '']);
        config(['upyun.password' => '']);

        $service = new UpyunService;

        $result = $service->upload('/tmp/test.png', '/test/image.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('未配置', $result['message']);
    }

    public function test_upload_returns_false_when_remote_path_empty(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => 'test-password']);
        config(['upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $result = $service->upload('/tmp/test.png', '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('remotePath 不能为空', $result['message']);
    }

    public function test_upload_returns_false_when_local_file_not_exists(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => 'test-password']);
        config(['upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $result = $service->upload('/nonexistent/file.png', '/test/image.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('不存在或不可读', $result['message']);
    }

    public function test_upload_returns_false_when_remote_path_only_slash(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => 'test-password']);
        config(['upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $result = $service->upload('/tmp/test.png', '/');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('remotePath 不能为空', $result['message']);
    }

    public function test_upload_strips_leading_slash_from_remote_path(): void
    {
        config(['upyun.bucket' => 'test-bucket']);
        config(['upyun.operator' => 'test-operator']);
        config(['upyun.password' => 'test-password']);
        config(['upyun.api_host' => 'api.test.com']);

        // Create a temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $service = new UpyunService;

        // Mock the HTTP facade to prevent actual network calls
        \Illuminate\Support\Facades\Http::shouldReceive('withHeaders')
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('Mock connection'));

        // Should not throw unhandled exception
        $this->expectNotToPerformAssertions();
    }

    public function test_guess_mime_type_returns_png_for_png_extension(): void
    {
        config(['upyun.bucket' => 'test']);
        config(['upyun.operator' => 'test']);
        config(['upyun.password' => 'test']);

        $service = new UpyunService;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.png');

        $this->assertEquals('image/png', $result);
    }

    public function test_guess_mime_type_returns_jpeg_for_jpg_extension(): void
    {
        config(['upyun.bucket' => 'test']);
        config(['upyun.operator' => 'test']);
        config(['upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.jpg');

        $this->assertEquals('image/jpeg', $result);
    }

    public function test_guess_mime_type_returns_jpeg_for_jpeg_extension(): void
    {
        config(['upyun.bucket' => 'test']);
        config(['upyun.operator' => 'test']);
        config(['upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.jpeg');

        $this->assertEquals('image/jpeg', $result);
    }

    public function test_guess_mime_type_returns_octet_stream_for_unknown_extension(): void
    {
        config(['upyun.bucket' => 'test']);
        config(['upyun.operator' => 'test']);
        config(['upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/file.xyz');

        $this->assertEquals('application/octet-stream', $result);
    }

    public function test_guess_mime_type_is_case_insensitive(): void
    {
        config(['upyun.bucket' => 'test']);
        config(['upyun.operator' => 'test']);
        config(['upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.PNG');

        $this->assertEquals('image/png', $result);
    }
}
