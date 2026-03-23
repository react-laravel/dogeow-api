<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameMapDefinitionTest extends TestCase
{
    use RefreshDatabase;

    protected GameMapDefinition $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->map = new GameMapDefinition;
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->map->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('act', $fillable);
        $this->assertContains('monster_ids', $fillable);
        $this->assertContains('background', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('is_active', $fillable);
    }

    public function test_model_uses_correct_hidden_attributes(): void
    {
        $hidden = $this->map->getHidden();
        $this->assertContains('min_level', $hidden);
        $this->assertContains('max_level', $hidden);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->map->getCasts();
        $this->assertArrayHasKey('monster_ids', $casts);
        $this->assertArrayHasKey('is_active', $casts);
        $this->assertEquals('array', $casts['monster_ids']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_can_enter_always_returns_true(): void
    {
        $map = new GameMapDefinition;
        $this->assertTrue($map->canEnter(1));
        $this->assertTrue($map->canEnter(50));
        $this->assertTrue($map->canEnter(100));
    }

    public function test_get_level_range_text_returns_no_restriction(): void
    {
        $map = new GameMapDefinition;
        // method currently returns a localized string, so just ensure
        // it mentions the idea of "no level restriction" in either
        // language.
        $text = $map->getLevelRangeText();
        $this->assertTrue(str_contains($text, 'No Level') || str_contains($text, '等级'));
    }

    public function test_character_maps_relationship(): void
    {
        $map = new GameMapDefinition;
        $this->assertInstanceOf(HasMany::class, $map->characterMaps());
    }

    public function test_get_monsters_returns_empty_array_when_no_monster_ids(): void
    {
        $map = new GameMapDefinition(['monster_ids' => null]);
        $monsters = $map->getMonsters();
        $this->assertIsArray($monsters);
        $this->assertEmpty($monsters);
    }

    public function test_get_monsters_returns_empty_array_when_monster_ids_empty(): void
    {
        $map = new GameMapDefinition(['monster_ids' => []]);
        $monsters = $map->getMonsters();
        $this->assertIsArray($monsters);
        $this->assertEmpty($monsters);
    }

    public function test_get_monsters_filters_invalid_ids(): void
    {
        $map = new GameMapDefinition(['monster_ids' => [0, -1, null, 'abc']]);
        $monsters = $map->getMonsters();
        $this->assertIsArray($monsters);
        $this->assertEmpty($monsters);
    }

    public function test_get_monsters_returns_active_monsters_only(): void
    {
        $activeMonster = GameMonsterDefinition::create([
            'name' => 'Wolf',
            'type' => 'normal',
            'level' => 3,
            'hp_base' => 30,
            'attack_base' => 8,
            'defense_base' => 2,
            'experience_base' => 5,
            'is_active' => true,
        ]);
        $inactiveMonster = GameMonsterDefinition::create([
            'name' => 'Ghost',
            'type' => 'normal',
            'level' => 5,
            'hp_base' => 50,
            'attack_base' => 10,
            'defense_base' => 4,
            'experience_base' => 9,
            'is_active' => false,
        ]);

        $map = new GameMapDefinition([
            'monster_ids' => [$activeMonster->id, $inactiveMonster->id, $activeMonster->id],
        ]);

        $monsters = $map->getMonsters();

        $this->assertCount(1, $monsters);
        $this->assertSame($activeMonster->id, $monsters[0]->id);
    }
}
