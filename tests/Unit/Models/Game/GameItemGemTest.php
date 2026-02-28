<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameItemGem;
use Tests\TestCase;

class GameItemGemTest extends TestCase
{
    protected GameItemGem $gem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gem = new GameItemGem();
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->gem->getFillable();
        $this->assertContains('item_id', $fillable);
        $this->assertContains('gem_definition_id', $fillable);
        $this->assertContains('socket_index', $fillable);
    }

    public function test_item_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->gem, 'item'));
    }

    public function test_gem_definition_relationship_exists(): void
    {
        $this->assertTrue(method_exists($this->gem, 'gemDefinition'));
    }

    public function test_get_gem_stats_returns_empty_array_when_no_definition(): void
    {
        $gem = new GameItemGem();
        $stats = $gem->getGemStats();
        $this->assertIsArray($stats);
        $this->assertEmpty($stats);
    }
}
