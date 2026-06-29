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

    public function test_calculate_buy_price_falls_back_to_stat_formula_when_base_stats_price_is_not_numeric(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 'free'];
        $definition->required_level = 1;
        $definition->type = 'weapon';

        $result = $this->calculator->calculateBuyPrice($definition);

        $this->assertSame(1, $result);
    }

    public function test_calculate_buy_price_is_twice_equipment_sell_price(): void
    {
        $definition = new GameItemDefinition;
        $definition->buy_price = 0;
        $definition->base_stats = ['price' => 0];
        $definition->required_level = 5;
        $definition->type = 'weapon';

        $stats = [
            'attack' => 10,
            'crit_rate' => 0.05,
        ];

        $item = new GameItem;
        $item->definition = $definition;
        $item->stats = $stats;
        $item->quality = 'common';

        $buyPrice = $this->calculator->calculateBuyPrice($definition, $stats, 'common');
        $sellPrice = $this->calculator->calculateSellPrice($item);

        $this->assertEqualsWithDelta($sellPrice * 2, $buyPrice, 1);
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

        $this->assertSame(100, $result);
    }

    public function test_calculate_sell_price_for_gem_uses_gem_stats(): void
    {
        $definition = new GameItemDefinition;
        $definition->type = 'gem';
        $definition->gem_stats = ['attack' => 10];

        $item = new GameItem;
        $item->definition = $definition;

        $this->assertSame(30, $this->calculator->calculateSellPrice($item));
        $this->assertSame(60, $this->calculator->calculateBuyPrice($definition));
    }
}
