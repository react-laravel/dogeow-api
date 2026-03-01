<?php

namespace Tests\Unit\Requests\Word;

use App\Http\Requests\Word\MarkWordRequest;
use Tests\TestCase;

class MarkWordRequestTest extends TestCase
{
    private MarkWordRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new MarkWordRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_remembered_is_required(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('remembered', $rules);
        $this->assertContains('required', $rules['remembered']);
    }

    public function test_remembered_must_be_boolean(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('boolean', $rules['remembered']);
    }
}
