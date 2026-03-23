<?php

namespace Tests\Unit\Requests\Word;

use App\Http\Requests\Word\CreateWordRequest;
use Tests\TestCase;

class CreateWordRequestTest extends TestCase
{
    private CreateWordRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CreateWordRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_content_is_required(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('content', $rules);
        $this->assertStringContainsString('required', $rules['content']);
    }

    public function test_content_must_be_unique(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('unique', $rules['content']);
    }

    public function test_phonetic_us_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('phonetic_us', $rules);
        $this->assertStringContainsString('nullable', $rules['phonetic_us']);
    }

    public function test_explanation_is_optional(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('explanation', $rules);
        $this->assertStringContainsString('nullable', $rules['explanation']);
    }

    public function test_example_sentences_is_array(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('example_sentences', $rules);
        $this->assertStringContainsString('array', $rules['example_sentences']);
    }

    public function test_example_sentences_has_nested_validation(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('example_sentences.*.en', $rules);
        $this->assertArrayHasKey('example_sentences.*.zh', $rules);
    }

    public function test_education_level_codes_validation(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('education_level_codes.*', $rules);
        $this->assertStringContainsString('in', $rules['education_level_codes.*']);
    }

    public function test_messages_in_chinese(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('不能为空', $messages['content.required']);
        $this->assertStringContainsString('已存在', $messages['content.unique']);
    }
}
