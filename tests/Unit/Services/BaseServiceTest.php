<?php

namespace Tests\Unit\Services;

use App\Services\BaseService;
use Tests\TestCase;

class TestBaseService extends BaseService
{
    public function testSuccess(array $data = [], string $message = 'Success'): array
    {
        return $this->success($data, $message);
    }

    public function testError(string $message = 'Test error', array $errors = []): array
    {
        return $this->error($message, $errors);
    }

    public function testSanitizeString(string $input): string
    {
        return $this->sanitizeString($input);
    }

    public function testValidateStringLength(string $input, int $min, int $max): array
    {
        return $this->validateStringLength($input, $min, $max, 'test_field');
    }

    public function testHandleException(\Throwable $e, string $operation = 'operation'): array
    {
        return $this->handleException($e, $operation);
    }
}

class BaseServiceTest extends TestCase
{
    private TestBaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TestBaseService;
    }

    public function test_success_returns_array_with_success_flag(): void
    {
        $result = $this->service->testSuccess();

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    public function test_success_includes_message(): void
    {
        $result = $this->service->testSuccess();

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Success', $result['message']);
    }

    public function test_success_includes_custom_data(): void
    {
        $result = $this->service->testSuccess(['data' => 'test']);

        $this->assertArrayHasKey('data', $result);
    }

    public function test_error_returns_array_with_success_false(): void
    {
        $result = $this->service->testError();

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
    }

    public function test_error_includes_message(): void
    {
        $result = $this->service->testError();

        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Test error', $result['message']);
    }

    public function test_error_includes_errors(): void
    {
        $result = $this->service->testError('Test error', ['field' => 'error']);

        $this->assertArrayHasKey('errors', $result);
    }

    public function test_error_with_empty_errors_omits_errors_key(): void
    {
        $result = $this->service->testError('Error only');

        $this->assertArrayNotHasKey('errors', $result);
    }

    public function test_success_with_custom_message(): void
    {
        $result = $this->service->testSuccess([], 'Custom success');

        $this->assertEquals('Custom success', $result['message']);
    }

    public function test_handle_exception_returns_error_array(): void
    {
        $exception = new \RuntimeException('Something broke');

        $result = $this->service->testHandleException($exception, 'test op');

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to test op', $result['message']);
    }

    public function test_sanitize_string_trims_whitespace(): void
    {
        $result = $this->service->testSanitizeString('  hello  ');

        $this->assertEquals('hello', $result);
    }

    public function test_sanitize_string_removes_html_tags(): void
    {
        $result = $this->service->testSanitizeString('<b>hello</b>');

        $this->assertEquals('hello', $result);
    }

    public function test_validate_string_length_returns_valid_for_good_input(): void
    {
        $result = $this->service->testValidateStringLength('hello', 1, 10);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_string_length_returns_error_for_too_short(): void
    {
        $result = $this->service->testValidateStringLength('hi', 5, 10);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_string_length_returns_error_for_too_long(): void
    {
        $result = $this->service->testValidateStringLength('hello world', 1, 5);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
