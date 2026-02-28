<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameCharacterSkill;
use Tests\TestCase;

class GameCharacterSkillTest extends TestCase
{
    protected GameCharacterSkill $characterSkill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->characterSkill = new GameCharacterSkill();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->characterSkill->getFillable();
        $this->assertContains('character_id', $fillable);
        $this->assertContains('skill_id', $fillable);
    }

    public function test_character_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->characterSkill, 'character'));
    }

    public function test_skill_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->characterSkill, 'skill'));
    }
}
