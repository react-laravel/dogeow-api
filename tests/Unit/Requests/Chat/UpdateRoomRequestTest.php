<?php

namespace Tests\Unit\Requests\Chat;

use App\Http\Requests\Chat\UpdateRoomRequest;
use Tests\TestCase;

class UpdateRoomRequestTest extends TestCase
{
    private UpdateRoomRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new UpdateRoomRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_requires_name(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_rules_has_optional_description(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('description', $rules);
    }

    public function test_attributes_returns_values(): void
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('name', $attributes);
    }

    public function test_messages_returns_values(): void
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
    }
}
