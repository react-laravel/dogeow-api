<?php

namespace Tests\Unit\Services;

use App\Services\UpyunService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpyunServiceTest extends TestCase
{
    public function test_is_configured_returns_true_when_all_config_set(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_bucket_empty(): void
    {
        config(['services.upyun.bucket' => '']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);

        $service = new UpyunService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_operator_empty(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => '']);
        config(['services.upyun.password' => 'test-password']);

        $service = new UpyunService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_false_when_password_empty(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => '']);

        $service = new UpyunService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_upload_returns_false_when_not_configured(): void
    {
        config(['services.upyun.bucket' => '']);
        config(['services.upyun.operator' => '']);
        config(['services.upyun.password' => '']);

        $service = new UpyunService;

        $result = $service->upload('/tmp/test.png', '/test/image.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('未配置', $result['message']);
    }

    public function test_upload_returns_false_when_remote_path_empty(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $result = $service->upload('/tmp/test.png', '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('remotePath 不能为空', $result['message']);
    }

    public function test_upload_returns_false_when_local_file_not_exists(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $result = $service->upload('/nonexistent/file.png', '/test/image.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('不存在或不可读', $result['message']);
    }

    public function test_upload_returns_false_when_remote_path_only_slash(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);

        $service = new UpyunService;

        $result = $service->upload('/tmp/test.png', '/');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('remotePath 不能为空', $result['message']);
    }

    public function test_upload_strips_leading_slash_from_remote_path(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);
        config(['services.upyun.domain' => 'https://cdn.example.com']);

        $tempFile = tempnam(sys_get_temp_dir(), 'upyun_');
        file_put_contents($tempFile, 'test content');

        try {
            Http::fake([
                'https://api.test.com/*' => Http::response('', 200),
            ]);

            $service = new UpyunService;
            $result = $service->upload($tempFile, '/test/image.png');

            $this->assertTrue($result['success']);
            $this->assertSame('/test/image.png', $result['path']);
            $this->assertSame('https://cdn.example.com/test/image.png', $result['url']);

            Http::assertSentCount(1);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_upload_returns_success_with_null_public_url_when_domain_not_configured(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);
        config(['services.upyun.domain' => null]);

        $tempFile = tempnam(sys_get_temp_dir(), 'upyun_');
        file_put_contents($tempFile, 'test content');

        try {
            Http::fake([
                'https://api.test.com/*' => Http::response('', 200),
            ]);

            $service = new UpyunService;
            $result = $service->upload($tempFile, '/assets/sample.jpg');

            $this->assertTrue($result['success']);
            $this->assertSame('/assets/sample.jpg', $result['path']);
            $this->assertNull($result['url']);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_upload_returns_error_when_remote_api_fails(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);

        $tempFile = tempnam(sys_get_temp_dir(), 'upyun_');
        file_put_contents($tempFile, 'test content');

        try {
            Http::fake([
                'https://api.test.com/*' => Http::response('forbidden', 403),
            ]);

            $service = new UpyunService;
            $result = $service->upload($tempFile, '/assets/fail.jpg');

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('又拍云上传失败: 403', $result['message']);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_upload_uses_explicit_content_type_when_provided(): void
    {
        config(['services.upyun.bucket' => 'test-bucket']);
        config(['services.upyun.operator' => 'test-operator']);
        config(['services.upyun.password' => 'test-password']);
        config(['services.upyun.api_host' => 'api.test.com']);

        $tempFile = tempnam(sys_get_temp_dir(), 'upyun_');
        file_put_contents($tempFile, 'content');

        try {
            Http::fake([
                'https://api.test.com/*' => Http::response('', 200),
            ]);

            $service = new UpyunService;
            $result = $service->upload($tempFile, '/custom/file.bin', 'application/custom-bin');

            $this->assertTrue($result['success']);

            Http::assertSent(function ($request): bool {
                return (string) $request->header('Content-Type')[0] === 'application/custom-bin';
            });
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_guess_mime_type_returns_png_for_png_extension(): void
    {
        config(['services.upyun.bucket' => 'test']);
        config(['services.upyun.operator' => 'test']);
        config(['services.upyun.password' => 'test']);

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
        config(['services.upyun.bucket' => 'test']);
        config(['services.upyun.operator' => 'test']);
        config(['services.upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.jpg');

        $this->assertEquals('image/jpeg', $result);
    }

    public function test_guess_mime_type_returns_jpeg_for_jpeg_extension(): void
    {
        config(['services.upyun.bucket' => 'test']);
        config(['services.upyun.operator' => 'test']);
        config(['services.upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.jpeg');

        $this->assertEquals('image/jpeg', $result);
    }

    public function test_guess_mime_type_returns_octet_stream_for_unknown_extension(): void
    {
        config(['services.upyun.bucket' => 'test']);
        config(['services.upyun.operator' => 'test']);
        config(['services.upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/file.xyz');

        $this->assertEquals('application/octet-stream', $result);
    }

    public function test_guess_mime_type_is_case_insensitive(): void
    {
        config(['services.upyun.bucket' => 'test']);
        config(['services.upyun.operator' => 'test']);
        config(['services.upyun.password' => 'test']);

        $service = new UpyunService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('guessMimeType');
        $method->setAccessible(true);

        $result = $method->invoke($service, '/path/to/image.PNG');

        $this->assertEquals('image/png', $result);
    }
}
