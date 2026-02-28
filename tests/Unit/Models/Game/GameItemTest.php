<?php

namespace Tests\Unit\Models\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use Tests\TestCase;

class GameItemTest extends TestCase
{
    protected GameItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->item = new GameItem;
    }

    public function test_model_uses_correct_fillable_attributes(): void
    {
        $fillable = $this->item->getFillable();
        $this->assertContains('character_id', $fillable);
        $this->assertContains('definition_id', $fillable);
        $this->assertContains('quality', $fillable);
        $this->assertContains('stats', $fillable);
        $this->assertContains('affixes', $fillable);
        $this->assertContains('is_in_storage', $fillable);
        $this->assertContains('is_equipped', $fillable);
        $this->assertContains('quantity', $fillable);
        $this->assertContains('slot_index', $fillable);
        $this->assertContains('sockets', $fillable);
        $this->assertContains('sell_price', $fillable);
    }

    public function test_model_uses_correct_casts(): void
    {
        $casts = $this->item->getCasts();
        $this->assertArrayHasKey('stats', $casts);
        $this->assertArrayHasKey('affixes', $casts);
        $this->assertArrayHasKey('is_in_storage', $casts);
        $this->assertArrayHasKey('is_equipped', $casts);
        $this->assertEquals('array', $casts['stats']);
        $this->assertEquals('array', $casts['affixes']);
        $this->assertEquals('boolean', $casts['is_in_storage']);
        $this->assertEquals('boolean', $casts['is_equipped']);
    }

    public function test_model_has_correct_quality_constants(): void
    {
        $expectedQualities = ['common', 'magic', 'rare', 'legendary', 'mythic'];
        $this->assertEquals($expectedQualities, GameItem::QUALITIES);
    }

    public function test_model_has_correct_quality_colors(): void
    {
        $this->assertEquals('#ffffff', GameItem::QUALITY_COLORS['common']);
        $this->assertEquals('#6888ff', GameItem::QUALITY_COLORS['magic']);
        $this->assertEquals('#ffcc00', GameItem::QUALITY_COLORS['rare']);
        $this->assertEquals('#ff8000', GameItem::QUALITY_COLORS['legendary']);
        $this->assertEquals('#00ff00', GameItem::QUALITY_COLORS['mythic']);
    }

    public function test_model_has_correct_quality_multipliers(): void
    {
        $this->assertEquals(1.0, GameItem::QUALITY_MULTIPLIERS['common']);
        $this->assertEquals(1.3, GameItem::QUALITY_MULTIPLIERS['magic']);
        $this->assertEquals(1.6, GameItem::QUALITY_MULTIPLIERS['rare']);
        $this->assertEquals(2.0, GameItem::QUALITY_MULTIPLIERS['legendary']);
        $this->assertEquals(2.5, GameItem::QUALITY_MULTIPLIERS['mythic']);
    }

    public function test_model_has_stat_prices_constant(): void
    {
        $this->assertEquals(3, GameItem::STAT_PRICES['attack']);
        $this->assertEquals(2, GameItem::STAT_PRICES['defense']);
        $this->assertEquals(0.5, GameItem::STAT_PRICES['max_hp']);
        $this->assertEquals(0.3, GameItem::STAT_PRICES['max_mana']);
        $this->assertEquals(500, GameItem::STAT_PRICES['crit_rate']);
        $this->assertEquals(200, GameItem::STAT_PRICES['crit_damage']);
    }

    public function test_model_has_type_price_multipliers_constant(): void
    {
        $this->assertEquals(1.2, GameItem::TYPE_PRICE_MULTIPLIERS['weapon']);
        $this->assertEquals(1.0, GameItem::TYPE_PRICE_MULTIPLIERS['helmet']);
        $this->assertEquals(1.3, GameItem::TYPE_PRICE_MULTIPLIERS['armor']);
        $this->assertEquals(0.8, GameItem::TYPE_PRICE_MULTIPLIERS['gloves']);
        $this->assertEquals(0.8, GameItem::TYPE_PRICE_MULTIPLIERS['boots']);
    }

    public function test_normalize_stats_precision_rounds_numeric_values(): void
    {
        $input = [
            'attack' => 10.123456789,
            'defense' => 5.999999999,
            'crit_rate' => 0.020000000000000004,
        ];
        $result = GameItem::normalizeStatsPrecision($input, 4);
        $this->assertEquals(10.1235, $result['attack']);
        $this->assertEquals(6.0, $result['defense']);
        $this->assertEquals(0.02, $result['crit_rate']);
    }

    public function test_normalize_stats_precision_preserves_non_numeric_values(): void
    {
        $input = [
            'name' => 'test',
            'type' => 'weapon',
            'attack' => 10,
        ];
        $result = GameItem::normalizeStatsPrecision($input);
        $this->assertEquals('test', $result['name']);
        $this->assertEquals('weapon', $result['type']);
        $this->assertEquals(10, $result['attack']);
    }

    public function test_get_quality_color_returns_correct_color(): void
    {
        $item = new GameItem(['quality' => 'legendary']);
        $this->assertEquals('#ff8000', $item->getQualityColor());
    }

    public function test_get_quality_multiplier_returns_correct_multiplier(): void
    {
        $item = new GameItem(['quality' => 'rare']);
        $this->assertEquals(1.6, $item->getQualityMultiplier());
    }

    public function test_get_display_name_with_no_quality_prefix(): void
    {
        $definition = new GameItemDefinition(['name' => 'Test Sword']);
        $item = new GameItem(['quality' => 'common']);
        $item->definition = $definition;
        $this->assertEquals('Test Sword', $item->getDisplayName());
    }

    public function test_get_display_name_with_magic_prefix(): void
    {
        $definition = new GameItemDefinition(['name' => 'Test Sword']);
        $item = new GameItem(['quality' => 'magic']);
        $item->definition = $definition;
        $this->assertEquals('Magic Test Sword', $item->getDisplayName());
    }

    public function test_get_display_name_with_rare_prefix(): void
    {
        $definition = new GameItemDefinition(['name' => 'Test Sword']);
        $item = new GameItem(['quality' => 'rare']);
        $item->definition = $definition;
        $this->assertEquals('Rare Test Sword', $item->getDisplayName());
    }

    public function test_get_display_name_with_legendary_prefix(): void
    {
        $definition = new GameItemDefinition(['name' => 'Test Sword']);
        $item = new GameItem(['quality' => 'legendary']);
        $item->definition = $definition;
        $this->assertEquals('Legendary Test Sword', $item->getDisplayName());
    }

    public function test_get_display_name_with_mythic_prefix(): void
    {
        $definition = new GameItemDefinition(['name' => 'Test Sword']);
        $item = new GameItem(['quality' => 'mythic']);
        $item->definition = $definition;
        $this->assertEquals('Mythic Test Sword', $item->getDisplayName());
    }

    public function test_can_equip_returns_true_when_level_sufficient(): void
    {
        $character = new GameCharacter(['level' => 10]);
        $definition = new GameItemDefinition(['required_level' => 5]);
        $item = new GameItem;
        $item->definition = $definition;

        $result = $item->canEquip($character);
        $this->assertTrue($result['can_equip']);
        $this->assertNull($result['reason']);
    }

    public function test_can_equip_returns_false_when_level_insufficient(): void
    {
        $character = new GameCharacter(['level' => 5]);
        $definition = new GameItemDefinition(['required_level' => 10]);
        $item = new GameItem;
        $item->definition = $definition;

        $result = $item->canEquip($character);
        $this->assertFalse($result['can_equip']);
        $this->assertStringContainsString('Level', $result['reason']);
    }

    public function test_character_relationship(): void
    {
        $this->assertTrue(method_exists($this->item, 'character'));
    }

    public function test_definition_relationship(): void
    {
        $this->assertTrue(method_exists($this->item, 'definition'));
    }

    public function test_gems_relationship(): void
    {
        $this->assertTrue(method_exists($this->item, 'gems'));
    }

    public function test_get_total_stats_returns_base_stats(): void
    {
        $item = new GameItem(['stats' => ['attack' => 10, 'defense' => 5]]);
        $totalStats = $item->getTotalStats();
        $this->assertEquals(10, $totalStats['attack']);
        $this->assertEquals(5, $totalStats['defense']);
    }

    public function test_get_total_stats_includes_affixes(): void
    {
        $item = new GameItem([
            'stats' => ['attack' => 10],
            'affixes' => [['attack' => 5]],
        ]);
        $totalStats = $item->getTotalStats();
        $this->assertEquals(15, $totalStats['attack']);
    }
}
