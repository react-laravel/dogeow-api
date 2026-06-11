<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;

/**
 * ShopItemCreationService - handles item creation logic for shop transactions
 *
 * This service extracts the responsibility of creating GameItem records
 * from the shop service, following the Single Responsibility Principle.
 */
class ShopItemCreationService
{
    public function __construct(
        private InventoryItemCalculator $itemCalculator = new InventoryItemCalculator
    ) {}

    /**
     * Create a potion item for purchase
     */
    public function createPotionItem(GameCharacter $character, GameItemDefinition $definition, int $quantity, array $randomStats): GameItem
    {
        $tempItem = new GameItem([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $randomStats,
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => $quantity,
        ]);
        $tempItem->setRelation('definition', $definition);
        $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);

        $inventoryService = new GameInventoryService;

        return GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $randomStats,
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => $quantity,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sell_price' => $sellPrice,
        ]);
    }

    /**
     * Create an equipment item for purchase
     *
     * @return array<int, GameItem>
     */
    public function createEquipmentItems(
        GameCharacter $character,
        GameItemDefinition $definition,
        int $quantity,
        array $randomStats,
        string $quality = 'common',
    ): array {
        $tempItem = new GameItem([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => $quality,
            'stats' => $randomStats,
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
        ]);
        $tempItem->setRelation('definition', $definition);
        $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);

        $inventoryService = new GameInventoryService;
        $createdItems = [];

        for ($i = 0; $i < $quantity; $i++) {
            $createdItems[] = GameItem::create([
                'character_id' => $character->id,
                'definition_id' => $definition->id,
                'quality' => $quality,
                'stats' => $randomStats,
                'affixes' => [],
                'is_in_storage' => false,
                'quantity' => 1,
                'slot_index' => $inventoryService->findEmptySlot($character, false),
                'sell_price' => $sellPrice,
            ]);
        }

        return $createdItems;
    }

    /**
     * Create a gem item for purchase
     */
    public function createGemItem(GameCharacter $character, GameItemDefinition $definition, int $quantity): GameItem
    {
        $tempItem = new GameItem([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => $quantity,
        ]);
        $tempItem->setRelation('definition', $definition);
        $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);

        $inventoryService = new GameInventoryService;

        return GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => $quantity,
            'slot_index' => $inventoryService->findEmptySlot($character, false),
            'sell_price' => $sellPrice,
        ]);
    }

    /**
     * Add quantity to existing gem item or create new one
     *
     * @return array{item: GameItem, is_new: bool}
     */
    public function addGemToInventory(GameCharacter $character, GameItemDefinition $definition, int $quantity): array
    {
        /** @var GameItem|null $existingItem */
        $existingItem = $character->items()
            ->where('definition_id', $definition->id)
            ->where('is_in_storage', false)
            ->where('quality', 'common')
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();

            return ['item' => $existingItem, 'is_new' => false];
        }

        $item = $this->createGemItem($character, $definition, $quantity);

        return ['item' => $item, 'is_new' => true];
    }

    /**
     * Add quantity to existing potion item or create new one
     *
     * @return array{item: GameItem, is_new: bool}
     */
    public function addPotionToInventory(GameCharacter $character, GameItemDefinition $definition, int $quantity, array $randomStats): array
    {
        /** @var GameItem|null $existingItem */
        $existingItem = $character->items()
            ->where('definition_id', $definition->id)
            ->where('is_in_storage', false)
            ->where('quality', 'common')
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();

            return ['item' => $existingItem, 'is_new' => false];
        }

        $item = $this->createPotionItem($character, $definition, $quantity, $randomStats);

        return ['item' => $item, 'is_new' => true];
    }

    /**
     * Check if character has inventory space for items
     */
    public function hasInventorySpace(GameCharacter $character, int $itemCount, bool $isPotion = false): bool
    {
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        $inventorySize = GameInventoryService::INVENTORY_SIZE;

        if ($isPotion) {
            return $inventoryCount < $inventorySize;
        }

        return $inventoryCount + $itemCount <= $inventorySize;
    }
}
