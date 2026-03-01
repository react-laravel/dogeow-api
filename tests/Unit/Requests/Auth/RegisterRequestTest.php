<?php

namespace Tests\Unit\Requests\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use Tests\TestCase;

class RegisterRequestTest extends TestCase
{
    private RegisterRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RegisterRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_requires_name_email_password(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    public function test_validation_passes_with_valid_data(): void
    {
        $this->request->merge([
            'name' => 'Test User',
            'email' => 'test' . time() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_without_name(): void
    {
        $this->request->merge([
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_without_email(): void
    {
        $this->request->merge([
            'name' => 'Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_without_password(): void
    {
        $this->request->merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $validator = \Validator::make($this->request->all(), $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }
}
