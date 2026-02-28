<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameSkillDefinition;
use Tests\TestCase;

class GameSkillDefinitionTest extends TestCase
{
    protected GameSkillDefinition $skill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skill = new GameSkillDefinition();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->skill->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('class_restriction', $fillable);
        $this->assertContains('damage', $fillable);
        $this->assertContains('mana_cost', $fillable);
        $this->assertContains('cooldown', $fillable);
        $this->assertContains('icon', $fillable);
        $this->assertContains('effect_key', $fillable);
        $this->assertContains('effects', $fillable);
        $this->assertContains('target_type', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('max_level', $fillable);
        $this->assertContains('base_damage', $fillable);
        $this->assertContains('damage_per_level', $fillable);
        $this->assertContains('mana_cost_per_level', $fillable);
        $this->assertContains('skill_points_cost', $fillable);
        $this->assertContains('branch', $fillable);
        $this->assertContains('tier', $fillable);
        $this->assertContains('prerequisite_skill_id', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->skill->getCasts();
        $this->assertArrayHasKey('effects', $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertArrayHasKey('cooldown', $casts);
        $this->assertArrayHasKey('max_level', $casts);
        $this->assertArrayHasKey('base_damage', $casts);
        $this->assertArrayHasKey('damage_per_level', $casts);
        $this->assertArrayHasKey('mana_cost_per_level', $casts);
        $this->assertEquals('array', $casts['effects']);
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('float', $casts['cooldown']);
        $this->assertEquals('integer', $casts['max_level']);
    }

    public function test_model_has_correct_type_constants(): void
    {
        $this->assertEquals(['active', 'passive'], GameSkillDefinition::TYPES);
    }

    public function test_model_has_correct_class_restriction_constants(): void
    {
        $this->assertEquals(['warrior', 'mage', 'ranger', 'all'], GameSkillDefinition::CLASS_RESTRICTIONS);
    }

    public function test_can_learn_by_class_returns_true_when_class_restriction_is_all(): void
    {
        $skill = new GameSkillDefinition(['class_restriction' => 'all']);
        $this->assertTrue($skill->canLearnByClass('warrior'));
        $this->assertTrue($skill->canLearnByClass('mage'));
        $this->assertTrue($skill->canLearnByClass('ranger'));
    }

    public function test_can_learn_by_class_returns_true_when_class_matches(): void
    {
        $skill = new GameSkillDefinition(['class_restriction' => 'warrior']);
        $this->assertTrue($skill->canLearnByClass('warrior'));
    }

    public function test_can_learn_by_class_returns_false_when_class_does_not_match(): void
    {
        $skill = new GameSkillDefinition(['class_restriction' => 'warrior']);
        $this->assertFalse($skill->canLearnByClass('mage'));
        $this->assertFalse($skill->canLearnByClass('ranger'));
    }
}
