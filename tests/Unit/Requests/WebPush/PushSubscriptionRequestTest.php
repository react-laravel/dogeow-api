<?php

namespace Tests\Unit\Requests\WebPush;

use App\Http\Requests\WebPush\PushSubscriptionRequest;
use Tests\TestCase;

class PushSubscriptionRequestTest extends TestCase
{
    private PushSubscriptionRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new PushSubscriptionRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_requires_endpoint_and_keys(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('endpoint', $rules);
        $this->assertArrayHasKey('keys', $rules);
    }

    public function test_rules_requires_nested_keys(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('keys.p256dh', $rules);
        $this->assertArrayHasKey('keys.auth', $rules);
    }

    public function test_messages_returns_chinese_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('endpoint', $messages['endpoint.required']);
        $this->assertStringContainsString('keys', $messages['keys.required']);
    }
}
