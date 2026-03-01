<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMonsterDefinition;

class GameCombatLootService
{
    /**
     * Process death loot from monsters
     */
    public function processDeathLoot(GameCharacter $character, array $roundResult): array
    {
        $loot = $roundResult['loot'] ?? [];
        $monstersUpdated = $roundResult['monsters_updated'] ?? [];

        foreach ($monstersUpdated as $m) {
            if (! is_array($m) || ($m['hp'] ?? 0) > 0) {
                continue;
            }
            // Monster died, try to generate loot
            $monster = GameMonsterDefinition::query()->find($m['id'] ?? 0);
            if (! $monster) {
                continue;
            }

            // 发现怪物
            $character->discoverMonster($monster->id);

            $lootResult = $monster->generateLoot($character->level);
            if (isset($lootResult['item']) && ! isset($loot['item'])) {
                $item = $this->createItem($character, $lootResult['item']);
                if ($item) {
                    $loot['item'] = $item;
                }
            }
            if (isset($lootResult['potion']) && ! isset($loot['potion'])) {
                $potion = $this->createPotion($character, $lootResult['potion']);
                if ($potion) {
                    $loot['potion'] = $potion;
                }
            }
        }

        return $loot;
    }

    /**
     * Distribute experience and copper to character
     */
    public function distributeRewards(GameCharacter $character, array $roundResult): array
    {
        $loot = $roundResult['loot'] ?? [];

        // Grant experience
        $expGained = $roundResult['experience_gained'] ?? 0;
        if ($expGained > 0) {
            $character->addExperience($expGained);
        }

        // Grant copper
        $copperGained = $roundResult['copper_gained'] ?? 0;
        if ($copperGained > 0) {
            $character->copper += $copperGained;
            $character->save();
            $loot = array_merge($loot, ['copper' => $copperGained]);
        }

        return [
            'loot' => $loot,
            'experience_gained' => $expGained,
            'copper_gained' => $copperGained,
        ];
    }

    /**
     * Create a loot item
     */
    public function createItem(GameCharacter $character, array $itemData): ?GameItem
    {
        $definition = GameItemDefinition::query()
            ->where('type', $itemData['type'])
            ->where('required_level', '<=', $itemData['level'])
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if (! $definition) {
            return null;
        }

        $inventoryService = new GameInventoryService;
        if ($character->items()->where('is_in_storage', false)->count() >= $inventoryService::INVENTORY_SIZE) {
            return null;
        }

        $quality = $itemData['quality'];
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$quality] ?? 1.0;
        $stats = [];
        /** @var array<string, mixed> $baseStatsArr */
        $baseStatsArr = $definition->base_stats ?? [];
        foreach ($baseStatsArr as $stat => $value) {
            $statValue = (int) ($value * $qualityMultiplier * (0.8 + rand(0, 40) / 100));
            if ($statValue !== 0) {
                $stats[$stat] = $statValue;
            }
        }

        // Affixes and sockets
        $affixes = [];
        $sockets = 0;
        if ($quality !== 'common') {
            $affixCount = match ($quality) {
                'magic' => rand(1, 2),
                'rare' => rand(2, 3),
                'legendary' => rand(3, 4),
                'mythic' => rand(4, 5),
                default => 0,
            };
            $possibleAffixes = [
                ['attack' => rand(5, 20)],
                ['defense' => rand(3, 15)],
                ['crit_rate' => rand(1, 5) / 100],
                ['crit_damage' => rand(10, 30) / 100],
                ['max_hp' => rand(20, 100)],
                ['max_mana' => rand(10, 50)],
            ];
            shuffle($possibleAffixes);
            $affixes = array_slice($possibleAffixes, 0, $affixCount);

            if (in_array($definition->type, ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet'])) {
                $sockets = match ($quality) {
                    'magic' => rand(0, 1),
                    'rare' => rand(1, 2),
                    'legendary' => rand(2, 3),
                    'mythic' => rand(3, 4),
                    default => 0,
                };
            }
        }

        $basePrice = $baseStatsArr['price'] ?? 10;
        $sellRatio = config('game.shop.sell_ratio', 0.3);
        $sellPrice = (int) ($basePrice * $qualityMultiplier * $sellRatio);

        $item = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => $quality,
            'stats' => $stats,
            'affixes' => $affixes,
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sockets' => $sockets,
            'sell_price' => 0, // Temporarily set to 0, will recalculate later
        ]);

        // Calculate sell price based on attributes
        $item->sell_price = $item->calculateSellPrice();
        $item->save();

        // 发现物品
        $character->discoverItem($definition->id);

        return $item->load('definition');
    }

    /**
     * Create a loot potion
     */
    public function createPotion(GameCharacter $character, array $potionData): ?GameItem
    {
        $potionConfigs = [
            'hp' => [
                'minor' => ['name' => '轻型生命药水', 'restore' => 50],
                'light' => ['name' => '生命药水', 'restore' => 100],
                'medium' => ['name' => '强效生命药水', 'restore' => 200],
                'full' => ['name' => '超级生命药水', 'restore' => 400],
            ],
            'mp' => [
                'minor' => ['name' => '轻型法力药水', 'restore' => 30],
                'light' => ['name' => '法力药水', 'restore' => 60],
                'medium' => ['name' => '强效法力药水', 'restore' => 120],
                'full' => ['name' => '超级法力药水', 'restore' => 240],
            ],
        ];
        $type = $potionData['sub_type'];
        $level = $potionData['level'];
        if (! isset($potionConfigs[$type][$level])) {
            return null;
        }
        $config = $potionConfigs[$type][$level];
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

        /** @var GameItem|null $existingPotion */
        $existingPotion = $character->items()
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')->where('sub_type', $type);
            })
            ->where('is_in_storage', false)
            ->first();
        if ($existingPotion) {
            $existingPotion->increment('quantity');
            $existingPotion->load('definition');

            return $existingPotion;
        }

        $inventoryService = new GameInventoryService;
        if ($character->items()->where('is_in_storage', false)->count() >= $inventoryService::INVENTORY_SIZE) {
            return null;
        }

        $definition = GameItemDefinition::query()
            ->where('type', 'potion')
            ->where('sub_type', $type)
            ->whereJsonContains('gem_stats->restore', $config['restore'])
            ->first();

        if (! $definition) {
            $definition = GameItemDefinition::create([
                'name' => $config['name'],
                'type' => 'potion',
                'sub_type' => $type,
                'base_stats' => [$statKey => $config['restore']],
                'required_level' => 1,
                'icon' => 'potion',
                'description' => "恢复{$config['restore']}点" . ($type === 'hp' ? '生命值' : '法力值'),
                'is_active' => true,
                'sockets' => 0,
                'gem_stats' => ['restore' => $config['restore']],
            ]);
        }

        /** @var GameItem $potion */
        $potion = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats ?? [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sockets' => 0,
            'sell_price' => 0,
        ]);

        $potion->sell_price = $potion->calculateSellPrice();
        $potion->save();

        $character->discoverItem($definition->id);
        $potion->load('definition');

        return $potion;
    }

    /**
     * Create a loot gem
     */
    public function createGem(GameCharacter $character, int $level): ?GameItem
    {
        $gemTypes = [
            ['attack' => rand(5, 15), 'name' => '攻击宝石'],
            ['defense' => rand(3, 10), 'name' => '防御宝石'],
            ['max_hp' => rand(20, 50), 'name' => '生命宝石'],
            ['max_mana' => rand(10, 30), 'name' => '法力宝石'],
            ['crit_rate' => rand(1, 3) / 100, 'name' => '暴击宝石'],
            ['crit_damage' => rand(5, 15) / 100, 'name' => '暴伤宝石'],
        ];

        $selectedGem = $gemTypes[array_rand($gemTypes)];
        $gemStats = $selectedGem;
        unset($gemStats['name']);

        $inventoryService = new GameInventoryService;
        if ($character->items()->where('is_in_storage', false)->count() >= $inventoryService::INVENTORY_SIZE) {
            return null;
        }

        // 根据宝石属性计算价格
        $gemValue = 0;
        foreach ($gemStats as $stat => $value) {
            $gemValue += (int) ($value * 100); // 每个属性点100金币
        }

        $definition = GameItemDefinition::create([
            'name' => $selectedGem['name'],
            'type' => 'gem',
            'sub_type' => null,
            'base_stats' => [],
            'required_level' => 1,
            'icon' => 'gem',
            'description' => '可镶嵌到装备上，提升属性',
            'is_active' => true,
            'sockets' => 0,
            'gem_stats' => $gemStats,
            'buy_price' => max(10, $gemValue), // 最低10金币
        ]);

        $gem = GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sockets' => 0,
        ]);

        // 发现物品
        $character->discoverItem($definition->id);

        return $gem->load('definition');
    }
}
