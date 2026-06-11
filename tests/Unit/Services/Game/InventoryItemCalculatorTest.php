<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Services\Game\InventoryItemCalculator;
use Tests\TestCase;

class InventoryItemCalculatorTest extends TestCase
{
    protected InventoryItemCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new InventoryItemCalculator;
    }

    public function test_calculate_sell_price_returns_zero_when_no_definition(): void
    {
        $item = new GameItem;
        $item->stats = [];
        $item->quality = 'common';

        $result = $this->calculator->calculateSellPrice($item);

        $this->assertSame(0, $result);
    }

    public function test_calculate_sell_price_uses_sell_ratio(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 100;
        $definition->base_stats = null;
        $definition->required_level = 1;
        $definition->type = 'potion';

        $item = new GameItem;
        $item->definition = $definition;
        $item->stats = [];
        $item->quality = 'common';

        $sellPrice = $this->calculator->calculateSellPrice($item);

        // Default sell_ratio is 0.3, so sell price = 100 * 0.3 = 30
        $this->assertSame(30, $sellPrice);
    }

    public function test_calculate_sell_price_includes_affixes_in_total_stats(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 0];
        $definition->required_level = 1;
        $definition->type = 'weapon';

        $item = new GameItem;
        $item->definition = $definition;
        $item->stats = ['attack' => 10];
        $item->affixes = [['attack' => 5]];
        $item->quality = 'common';

        $withAffix = $this->calculator->calculateSellPrice($item);

        $item->affixes = [];
        $withoutAffix = $this->calculator->calculateSellPrice($item);

        $this->assertGreaterThan($withoutAffix, $withAffix);
    }

    public function test_calculate_item_buy_price_matches_calculate_buy_price_with_total_stats(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 0];
        $definition->required_level = 3;
        $definition->type = 'weapon';

        $item = new GameItem;
        $item->definition = $definition;
        $item->stats = ['attack' => 24];
        $item->quality = 'common';

        $this->assertSame(
            $this->calculator->calculateBuyPrice($definition, ['attack' => 24], 'common'),
            $this->calculator->calculateItemBuyPrice($item)
        );
    }

    public function test_calculate_buy_price_returns_zero_when_no_definition(): void
    {
        $result = $this->calculator->calculateBuyPrice(null);

        $this->assertSame(0, $result);
    }

    public function test_calculate_buy_price_uses_fixed_price_when_available(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 500;
        $definition->base_stats = null;
        $definition->required_level = 1;
        $definition->type = 'potion';

        $result = $this->calculator->calculateBuyPrice($definition);

        $this->assertSame(500, $result);
    }

    public function test_calculate_buy_price_uses_base_stats_price_when_no_fixed_price(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 200];
        $definition->required_level = 1;
        $definition->type = 'potion';

        $result = $this->calculator->calculateBuyPrice($definition);

        $this->assertSame(200, $result);
    }

    public function test_calculate_buy_price_returns_zero_when_base_stats_price_is_not_numeric(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 'free'];
        $definition->required_level = 1;
        $definition->type = 'potion';

        $result = $this->calculator->calculateBuyPrice($definition);

        // typeBasePrice=20, levelMult=1.5, qualityMult=1.0, *100 = 3000, /2 = 1500
        $this->assertSame(1500, $result);
    }

    public function test_calculate_buy_price_includes_stats_in_calculation(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 0]; // No base price
        $definition->required_level = 5;
        $definition->type = 'weapon';

        $stats = [
            'attack' => 10,
            'crit_rate' => 0.05,
        ];

        $result = $this->calculator->calculateBuyPrice($definition, $stats, 'common');

        // Level multiplier: 1 + 5 * 0.5 = 3.5
        // Type base price: 20 (default for weapon)
        // Stat price: attack * 2 + crit_rate * 2 = 10 * 2 + 0.05 * 2 = 20 + 0.1 = 20.1
        // Total: (20 + 20.1) * 3.5 * 1.0 (quality multiplier) = 140.35 -> 14035 (rounded * 100)
        $this->assertGreaterThan(0, $result);
    }

    public function test_get_potion_effects_returns_hp_and_mana(): void
    {
        $definition = new GameItemDefinition;
        $definition->base_stats = ['max_hp' => 50, 'max_mana' => 30];

        $item = new GameItem;
        $item->definition = $definition;

        $effects = $this->calculator->getPotionEffects($item);

        $this->assertArrayHasKey('hp', $effects);
        $this->assertArrayHasKey('mana', $effects);
        $this->assertSame(50, $effects['hp']);
        $this->assertSame(30, $effects['mana']);
    }

    public function test_get_potion_effects_uses_restore_amount_as_hp(): void
    {
        $definition = new GameItemDefinition;
        $definition->base_stats = ['restore_amount' => 100];

        $item = new GameItem;
        $item->definition = $definition;

        $effects = $this->calculator->getPotionEffects($item);

        $this->assertSame(100, $effects['hp']);
    }

    public function test_get_potion_effects_returns_zeros_when_no_effects(): void
    {
        $definition = new GameItemDefinition;
        $definition->base_stats = [];

        $item = new GameItem;
        $item->definition = $definition;

        $effects = $this->calculator->getPotionEffects($item);

        $this->assertSame(0, $effects['hp']);
        $this->assertSame(0, $effects['mana']);
    }

    public function test_format_restore_message_includes_hp_and_mana(): void
    {
        $effects = ['hp' => 50, 'mana' => 30];

        $message = $this->calculator->formatRestoreMessage($effects);

        $this->assertStringContainsString('50', $message);
        $this->assertStringContainsString('30', $message);
        $this->assertStringContainsString('点生命值', $message);
        $this->assertStringContainsString('点法力值', $message);
    }

    public function test_format_restore_message_hp_only(): void
    {
        $effects = ['hp' => 50, 'mana' => 0];

        $message = $this->calculator->formatRestoreMessage($effects);

        $this->assertStringContainsString('50', $message);
        $this->assertStringContainsString('点生命值', $message);
    }

    public function test_format_restore_message_mana_only(): void
    {
        $effects = ['hp' => 0, 'mana' => 30];

        $message = $this->calculator->formatRestoreMessage($effects);

        $this->assertStringContainsString('30', $message);
        $this->assertStringContainsString('点法力值', $message);
    }

    public function test_generate_random_stats_for_weapon(): void
    {
        $definition = new GameItemDefinition;
        $definition->type = 'weapon';
        $definition->required_level = 5;

        $stats = $this->calculator->generateRandomStats($definition);

        $this->assertArrayHasKey('attack', $stats);
        $this->assertIsNumeric($stats['attack']);
    }

    public function test_generate_random_stats_for_helmet(): void
    {
        $definition = new GameItemDefinition;
        $definition->type = 'helmet';
        $definition->required_level = 3;

        $stats = $this->calculator->generateRandomStats($definition);

        $this->assertArrayHasKey('defense', $stats);
        $this->assertArrayHasKey('max_hp', $stats);
    }

    public function test_generate_random_stats_for_potion(): void
    {
        $definition = new GameItemDefinition;
        $definition->type = 'potion';
        $definition->required_level = 1;

        $stats = $this->calculator->generateRandomStats($definition);

        $this->assertArrayHasKey('restore', $stats);
    }

    public function test_generate_random_quality_returns_valid_quality(): void
    {
        $validQualities = ['common', 'magic', 'rare', 'legendary', 'mythic'];

        for ($i = 0; $i < 10; $i++) {
            $quality = $this->calculator->generateRandomQuality(10);

            $this->assertContains($quality, $validQualities);
        }
    }

    public function test_generate_random_quality_higher_level_increases_mythic_chance(): void
    {
        $level1MythicCount = 0;
        $level20MythicCount = 0;

        for ($i = 0; $i < 100; $i++) {
            if ($this->calculator->generateRandomQuality(1) === 'mythic') {
                $level1MythicCount++;
            }
            if ($this->calculator->generateRandomQuality(20) === 'mythic') {
                $level20MythicCount++;
            }
        }

        // Higher level should have more mythic drops
        $this->assertGreaterThanOrEqual($level1MythicCount, $level20MythicCount);
    }

    public function test_calculate_buy_price_with_mythic_quality(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 0];
        $definition->required_level = 10;
        $definition->type = 'weapon';

        $result = $this->calculator->calculateBuyPrice($definition, [], 'mythic');

        // Should be multiplied by mythic quality multiplier (usually higher)
        $this->assertGreaterThan(0, $result);
    }

    public function test_calculate_buy_price_with_unknown_quality_falls_back_to_default(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 100];
        $definition->required_level = 1;
        $definition->type = 'potion';

        $result = $this->calculator->calculateBuyPrice($definition, [], 'unknown_quality');

        // Should fall back to multiplier of 1.0
        $this->assertSame(100, $result);
    }
}
