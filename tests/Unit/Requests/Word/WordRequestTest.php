<?php

namespace Tests\Unit\Requests\Word;

use App\Http\Requests\Word\CreateWordRequest;
use App\Http\Requests\Word\MarkWordRequest;
use App\Http\Requests\Word\UpdateSettingRequest;
use Tests\TestCase;

class WordRequestTest extends TestCase
{
    public function test_create_word_request_authorize_returns_true(): void
    {
        $request = new CreateWordRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_create_word_request_rules(): void
    {
        $request = new CreateWordRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('content', $rules);
    }

    public function test_create_word_request_passes_with_valid_data(): void
    {
        $request = new CreateWordRequest;
        $request->merge([
            'content' => 'hello',
        ]);

        $validator = \Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->passes());
    }

    public function test_mark_word_request_authorize_returns_true(): void
    {
        $request = new MarkWordRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_mark_word_request_rules(): void
    {
        $request = new MarkWordRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('remembered', $rules);
    }

    public function test_update_setting_request_authorize_returns_true(): void
    {
        $request = new UpdateSettingRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_update_setting_request_rules(): void
    {
        $request = new UpdateSettingRequest;
        $rules = $request->rules();

        $this->assertNotEmpty($rules);
    }
}
