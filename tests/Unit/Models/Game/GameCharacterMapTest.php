<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameCharacterMap;
use Tests\TestCase;

class GameCharacterMapTest extends TestCase
{
    protected GameCharacterMap $characterMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->characterMap = new GameCharacterMap();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->characterMap->getFillable();
        $this->assertContains('character_id', $fillable);
        $this->assertContains('map_id', $fillable);
        $this->assertContains('progress', $fillable);
        $this->assertContains('best_score', $fillable);
        $this->assertContains('visited_at', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->characterMap->getCasts();
        $this->assertArrayHasKey('visited_at', $casts);
        $this->assertArrayHasKey('progress', $casts);
        $this->assertEquals('datetime', $casts['visited_at']);
    }

    public function test_character_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->characterMap, 'character'));
    }

    public function test_map_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->characterMap, 'map'));
    }
}
