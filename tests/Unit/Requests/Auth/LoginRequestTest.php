<?php

namespace Tests\Unit\Requests\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    private LoginRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LoginRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_requires_email_and_password(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertStringContainsString('required', $rules['email']);
        $this->assertStringContainsString('email', $rules['email']);
        $this->assertStringContainsString('required', $rules['password']);
    }

    public function test_messages_returns_chinese_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertEquals('邮箱是必填项。', $messages['email.required']);
        $this->assertEquals('邮箱格式不正确。', $messages['email.email']);
        $this->assertEquals('密码是必填项。', $messages['password.required']);
    }

    public function test_attributes_returns_chinese_attributes(): void
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('邮箱', $attributes['email']);
        $this->assertEquals('密码', $attributes['password']);
    }

    public function test_validation_passes_with_valid_data(): void
    {
        $this->request->merge([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_without_email(): void
    {
        $this->request->merge([
            'password' => 'password123',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email(): void
    {
        $this->request->merge([
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_without_password(): void
    {
        $this->request->merge([
            'email' => 'test@example.com',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}
