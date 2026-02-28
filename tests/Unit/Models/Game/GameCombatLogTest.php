<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameCombatLog;
use Tests\TestCase;

class GameCombatLogTest extends TestCase
{
    protected GameCombatLog $combatLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->combatLog = new GameCombatLog;
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->combatLog->getFillable();
        $this->assertContains('character_id', $fillable);
        $this->assertContains('map_id', $fillable);
        $this->assertContains('monster_id', $fillable);
        $this->assertContains('damage_dealt', $fillable);
        $this->assertContains('damage_taken', $fillable);
        $this->assertContains('victory', $fillable);
        $this->assertContains('loot_dropped', $fillable);
        $this->assertContains('experience_gained', $fillable);
        $this->assertContains('copper_gained', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->combatLog->getCasts();
        $this->assertArrayHasKey('victory', $casts);
        $this->assertArrayHasKey('loot_dropped', $casts);
        $this->assertArrayHasKey('skills_used', $casts);
        $this->assertEquals('boolean', $casts['victory']);
        $this->assertEquals('array', $casts['loot_dropped']);
    }

    public function test_character_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->combatLog, 'character'));
    }

    public function test_map_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->combatLog, 'map'));
    }

    public function test_monster_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->combatLog, 'monster'));
    }
}
