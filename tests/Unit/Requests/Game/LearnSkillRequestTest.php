<?php

namespace Tests\Unit\Requests\Game;

use App\Http\Requests\Game\LearnSkillRequest;
use Tests\TestCase;

class LearnSkillRequestTest extends TestCase
{
    private LearnSkillRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LearnSkillRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_has_skill_id_required(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('skill_id', $rules);
        $this->assertStringContainsString('required', $rules['skill_id']);
    }

    public function test_skill_id_must_exist_in_database(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('exists', $rules['skill_id']);
    }

    public function test_skill_id_must_be_integer(): void
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('integer', $rules['skill_id']);
    }

    public function test_messages_in_chinese(): void
    {
        $messages = $this->request->messages();

        $this->assertStringContainsString('技能ID', $messages['skill_id.required']);
    }
}
