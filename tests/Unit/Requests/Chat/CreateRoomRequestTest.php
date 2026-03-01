<?php

namespace Tests\Unit\Requests\Chat;

use App\Http\Requests\Chat\CreateRoomRequest;
use Tests\TestCase;

class CreateRoomRequestTest extends TestCase
{
    private CreateRoomRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CreateRoomRequest;
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
        $this->assertStringContainsString('unique:chat_rooms,name', $rules['name']);
    }

    public function test_description_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['description']);
        $this->assertStringContainsString('string', $rules['description']);
        $this->assertStringContainsString('max:1000', $rules['description']);
    }

    public function test_validation_attributes_contain_custom_attributes()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('description', $attributes);
        $this->assertEquals('Room Name', $attributes['name']);
        $this->assertEquals('Room Description', $attributes['description']);
    }

    public function test_validation_messages_contain_custom_messages()
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.unique', $messages);
        // max message is currently not defined in this request
        $this->assertArrayHasKey('description.max', $messages);
    }

    public function test_name_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('房间名称是必需的', $messages['name.required']);
    }

    public function test_name_unique_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('该房间名称已存在', $messages['name.unique']);
    }

    public function test_description_max_message()
    {
        $messages = $this->request->messages();

        // localized message
        $this->assertEquals('描述不能超过1000个字符', $messages['description.max']);
    }
}
