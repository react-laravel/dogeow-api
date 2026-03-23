<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\UploadBatchImagesRequest;
use Tests\TestCase;

class UploadBatchImagesRequestTest extends TestCase
{
    private UploadBatchImagesRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new UploadBatchImagesRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_requires_images_array(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('images.*', $rules);
        $this->assertStringContainsString('image', $rules['images.*']);
        $this->assertStringContainsString('max', $rules['images.*']);
    }

    public function test_messages_returns_chinese_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('图片', $messages['images.*.required']);
        $this->assertStringContainsString('20MB', $messages['images.*.max']);
    }

    public function test_attributes_returns_values(): void
    {
        $attributes = $this->request->attributes();

        $this->assertEquals('图片', $attributes['images.*']);
    }
}
