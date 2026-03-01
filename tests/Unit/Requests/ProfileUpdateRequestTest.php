<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ProfileUpdateRequest;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    private ProfileUpdateRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new ProfileUpdateRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_messages_returns_chinese_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertEquals('姓名是必填项', $messages['name.required']);
        $this->assertEquals('邮箱是必填项', $messages['email.required']);
    }
}
