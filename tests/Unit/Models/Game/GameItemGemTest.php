<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameItemGem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class GameItemGemTest extends TestCase
{
    protected GameItemGem $gem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gem = new GameItemGem;
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
        $this->assertInstanceOf(BelongsTo::class, $this->gem->item());
    }

    public function test_gem_definition_relationship_exists(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->gem->gemDefinition());
    }

    public function test_get_gem_stats_returns_empty_array_when_no_definition(): void
    {
        $gem = new GameItemGem;
        $stats = $gem->getGemStats();
        $this->assertIsArray($stats);
        $this->assertEmpty($stats);
    }

    public function test_get_gem_stats_returns_definition_stats(): void
    {
        $definition = new GameItemDefinition;
        $definition->gem_stats = ['attack' => 3];

        $gem = new GameItemGem;
        $gem->setRelation('gemDefinition', $definition);

        $this->assertSame(['attack' => 3], $gem->getGemStats());
    }
}
