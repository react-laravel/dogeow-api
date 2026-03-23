<?php

namespace Tests\Unit\Requests\Word;

use App\Http\Requests\Word\UpdateSettingRequest;
use Tests\TestCase;

class UpdateSettingRequestTest extends TestCase
{
    private UpdateSettingRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new UpdateSettingRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_daily_new_words_rules(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('daily_new_words', $rules);
        $this->assertContains('sometimes', $rules['daily_new_words']);
    }

    public function test_review_multiplier_rules(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('review_multiplier', $rules);
    }

    public function test_current_book_id_is_nullable(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('current_book_id', $rules);
    }

    public function test_is_auto_pronounce_is_boolean(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('is_auto_pronounce', $rules);
    }
}
