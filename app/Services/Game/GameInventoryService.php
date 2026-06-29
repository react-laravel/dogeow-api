<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Services\Game\Traits\UsesDistributedLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 背包服务类
 *
 * 负责背包相关的业务逻辑，包括物品装备、卸下，出售、移动等
 */
class GameInventoryService
{
    use UsesDistributedLock;

    /** 背包默认大小 */
    public const INVENTORY_SIZE = 50;

    /** 仓库默认大小 */
    public const STORAGE_SIZE = 50;

    /** 缓存键前缀 */
    private const CACHE_PREFIX = 'game_inventory:';

    /** 装备操作分布式锁超时时间（秒） */
    private const EQUIP_LOCK_TIMEOUT = 10;

    /** 物品操作分布式锁超时时间（秒） */
    private const ITEM_LOCK_TIMEOUT = 10;

    public function __construct(
        private InventoryEquipmentHelper $equipmentHelper = new InventoryEquipmentHelper
    ) {}

    /**
     * 获取背包物品
     *
     * @return array{inventory: \Illuminate\Database\Eloquent\Collection<int, GameItem>, storage: \Illuminate\Database\Eloquent\Collection<int, GameItem>, equipment: Collection<string, GameEquipment>, inventory_size: int, storage_size: int}
     */
    public function getInventory(GameCharacter $character): array
    {
        // 背包：不在仓库且未装备
        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $inventory */
        $inventory = $character->items()
            ->where('is_in_storage', false)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->with(['definition', 'gems.gemDefinition'])
            ->orderBy('slot_index')
            ->get();

        // 仓库：未装备
        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $storage */
        $storage = $character->items()
            ->where('is_in_storage', true)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->with(['definition', 'gems.gemDefinition'])
            ->orderBy('slot_index')
            ->get();

        /** @var Collection<string, GameEquipment> $equipment */
        $equipment = $character->equipment()
            ->with(['item.definition', 'item.gems.gemDefinition'])
            ->get()
            ->keyBy('slot');

        return [
            'inventory' => $inventory,
            'storage' => $storage,
            'equipment' => $equipment,
            'inventory_size' => self::INVENTORY_SIZE,
            'storage_size' => self::STORAGE_SIZE,
        ];
    }

    /**
     * 获取背包数据(用于 WebSocket 广播)
     *
     * @return array{inventory: array<int, array<int|string,mixed>>, storage: array<int, array<int|string,mixed>>, equipment: array<string, array<int|string,mixed>|null>, inventory_size: int, storage_size: int}
     */
    public function getInventoryForBroadcast(GameCharacter $character): array
    {
        $result = $this->getInventory($character);
        $equipmentArray = [];

        foreach ($result['equipment'] as $slot => $eq) {
            $equipmentArray[$slot] = isset($eq->item) ? $eq->item->toArray() : null;
        }

        return [
            'inventory' => array_values($result['inventory']->toArray()),
            'storage' => array_values($result['storage']->toArray()),
            'equipment' => $equipmentArray,
            'inventory_size' => $result['inventory_size'],
            'storage_size' => $result['storage_size'],
        ];
    }

    /**
     * 装备物品
     *
     * @return array{equipped_item: GameItem, equipped_slot: string, unequipped_item: GameItem|null, combat_stats: array<string,mixed>, stats_breakdown: array<string,mixed>}
     *
     * @throws \InvalidArgumentException
     */
    public function equipItem(GameCharacter $character, int $itemId): array
    {
        $item = $this->findItem($character, $itemId, false);

        // 检查是否可以装备
        $canEquip = $item->canEquip($character);
        if (! ($canEquip['can_equip'] ?? false)) {
            $reason = $canEquip['reason'] ?? '无法装备';
            if (! is_string($reason)) {
                $reason = is_scalar($reason) ? strval($reason) : '无法装备';
            }
            throw new \InvalidArgumentException($reason);
        }

        // 确定装备槽位
        $slot = $this->equipmentHelper->determineEquipmentSlot($character, $item);

        return $this->executeWithDistributedLock(
            lockKey: 'game:inventory:equip:' . $character->id,
            callback: fn () => $this->performEquip($character, $item, $slot),
            timeoutSeconds: self::EQUIP_LOCK_TIMEOUT,
        );
    }

    /**
     * 执行装备逻辑
     *
     * @return array{equipped_item: GameItem, equipped_slot: string, unequipped_item: GameItem|null, combat_stats: array<string,mixed>, stats_breakdown: array<string,mixed>}
     */
    private function performEquip(GameCharacter $character, GameItem $item, string $slot): array
    {
        return DB::transaction(function () use ($character, $item, $slot) {
            $equipmentSlot = $this->equipmentHelper->getOrCreateEquipmentSlot($character, $slot);
            $oldItem = $this->equipmentHelper->handleUnequipIfNeeded($character, $equipmentSlot);

            // 装备新物品
            $equipmentSlot->item_id = $item->id;
            $equipmentSlot->save();

            // 标记为已装备
            $item->is_equipped = true;
            $item->slot_index = null;
            $item->save();

            $character->refresh();
            $this->clearInventoryCache($character->id);

            $unequipped = null;
            if ($oldItem instanceof GameItem) {
                $unequipped = $oldItem->load('definition');
            }

            $equippedItem = $item->fresh();
            if (! ($equippedItem instanceof GameItem)) {
                $equippedItem = $item->load('definition');
            } else {
                $equippedItem->load('definition');
            }

            return [
                'equipped_item' => $equippedItem,
                'equipped_slot' => $slot,
                'unequipped_item' => $unequipped,
                'combat_stats' => $character->getCombatStats(),
                'stats_breakdown' => $character->getCombatStatsBreakdown(),
            ];
        });
    }

    /**
     * 卸下装备
     *
     * @return array{item: GameItem|null, combat_stats: array<string,mixed>, stats_breakdown: array<string,mixed>}
     *
     * @throws \InvalidArgumentException
     */
    public function unequipItem(GameCharacter $character, string $slot): array
    {
        $equipmentSlot = $character->equipment()->where('slot', $slot)->first();

        if (! $equipmentSlot instanceof GameEquipment || ! $equipmentSlot->item_id) {
            throw new \InvalidArgumentException('该槽位没有装备');
        }

        $emptySlot = $this->findEmptySlot($character, false);
        if ($emptySlot === null) {
            throw new \InvalidArgumentException('背包已满');
        }

        return $this->executeWithDistributedLock(
            lockKey: 'game:inventory:equip:' . $character->id,
            callback: fn () => $this->performUnequip($character, $equipmentSlot, $emptySlot),
            timeoutSeconds: self::EQUIP_LOCK_TIMEOUT,
        );
    }

    /**
     * 执行卸下装备逻辑
     *
     * @return array{item: GameItem|null, combat_stats: array<string,mixed>, stats_breakdown: array<string,mixed>}
     */
    private function performUnequip(GameCharacter $character, GameEquipment $equipmentSlot, int $emptySlot): array
    {
        return DB::transaction(function () use ($character, $equipmentSlot, $emptySlot) {
            $itemId = $equipmentSlot->item_id;
            $item = $itemId ? GameItem::with('definition')->find($itemId) : null;

            if ($item instanceof GameItem) {
                $item->is_equipped = false;
                $item->slot_index = $emptySlot;
                $item->save();
            }

            $equipmentSlot->item_id = null;
            $equipmentSlot->save();

            $character->refresh();
            $this->clearInventoryCache($character->id);

            return [
                'item' => $item,
                'combat_stats' => $character->getCombatStats(),
                'stats_breakdown' => $character->getCombatStatsBreakdown(),
            ];
        });
    }

    /**
     * 出售物品
     *
     * @return array{copper:int, sell_price:int}
     *
     * @throws \InvalidArgumentException
     */
    public function sellItem(GameCharacter $character, int $itemId, int $quantity = 1): array
    {
        return $this->executeWithDistributedLock(
            lockKey: 'game:inventory:item:' . $character->id . ':' . $itemId,
            callback: fn () => $this->performSell($character, $itemId, $quantity),
            timeoutSeconds: self::ITEM_LOCK_TIMEOUT,
        );
    }

    /**
     * 执行出售逻辑
     *
     * @return array{copper:int, sell_price:int}
     */
    private function performSell(GameCharacter $character, int $itemId, int $quantity): array
    {
        $item = $this->findItem($character, $itemId);

        if ($this->equipmentHelper->isItemEquipped($character, $itemId)) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            throw new \InvalidArgumentException('物品数量不足');
        }

        $sellPrice = $item->calculateSellPrice() * $quantity;

        return DB::transaction(function () use ($character, $item, $quantity, $sellPrice) {
            $character->copper += $sellPrice;
            $character->save();

            if ($item->quantity > $quantity) {
                $item->quantity -= $quantity;
                $item->save();
            } else {
                $item->delete();
            }

            $this->clearInventoryCache($character->id);

            return [
                'copper' => $character->copper,
                'sell_price' => $sellPrice,
            ];
        });
    }

    /**
     * 移动物品
     *
     * @return array{item: GameItem}
     *
     * @throws \InvalidArgumentException
     */
    public function moveItem(GameCharacter $character, int $itemId, bool $toStorage, ?int $slotIndex = null): array
    {
        $item = $this->findItem($character, $itemId, false);
        $this->checkStorageSpace($character, $toStorage);

        $item->is_in_storage = $toStorage;
        $item->slot_index = $slotIndex ?? $this->findEmptySlot($character, $toStorage);
        $item->save();

        $this->clearInventoryCache($character->id);

        return ['item' => $item];
    }

    /**
     * 整理背包
     *
     * @return array{inventory: Collection<int, GameItem>}
     */
    public function sortInventory(GameCharacter $character, string $sortBy = 'default'): array
    {
        $query = $character->items()
            ->where('is_in_storage', false)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->with('definition');

        $items = $this->sortItems($query, $sortBy);

        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                $item->slot_index = null;
                $item->save();
            }

            $slotIndex = 0;
            foreach ($items as $item) {
                $item->slot_index = $slotIndex++;
                $item->save();
            }
        });

        $this->clearInventoryCache($character->id);

        return ['inventory' => $items];
    }

    /**
     * 批量出售指定品质的物品
     *
     * @return array{count: int, total_price: int, copper: int}
     */
    public function sellItemsByQuality(GameCharacter $character, string $quality): array
    {
        $items = $this->getSellableItemsByQuality($character, $quality);

        if ($items->isEmpty()) {
            return [
                'count' => 0,
                'total_price' => 0,
                'copper' => $character->copper,
            ];
        }

        return DB::transaction(function () use ($character, $items) {
            $totalPrice = 0;
            $count = 0;
            $equippedItemIds = $character->equipment()->pluck('item_id');

            foreach ($items as $item) {
                if ($equippedItemIds->contains($item->id)) {
                    continue;
                }

                $price = $item->calculateSellPrice() * $item->quantity;
                $totalPrice += $price;
                $count++;
                $item->delete();
            }

            $character->copper += $totalPrice;
            $character->save();
            $this->clearInventoryCache($character->id);

            return [
                'count' => $count,
                'total_price' => $totalPrice,
                'copper' => $character->copper,
            ];
        });
    }

    /**
     * 更新自动回收设置，并按新阈值清理背包中符合条件的物品
     *
     * @return array{
     *     character: GameCharacter,
     *     recycled: array{count: int, total_price: int, copper: int}
     * }
     */
    public function updateAutoRecycleSettings(GameCharacter $character, ?int $maxValue): array
    {
        $normalized = ($maxValue !== null && $maxValue > 0) ? $maxValue : null;

        return DB::transaction(function () use ($character, $normalized) {
            $character->auto_recycle_max_value = $normalized;
            $character->save();

            $recycled = $normalized === null
                ? ['count' => 0, 'total_price' => 0, 'copper' => $character->copper]
                : $this->sellItemsAtOrBelowValue($character, $normalized);

            return [
                'character' => $character->fresh(),
                'recycled' => $recycled,
            ];
        });
    }

    /**
     * 背包满时为掉落腾格：自动出售背包中同类型单价最低的可售物品
     *
     * @return array{
     *     sold_item_id: int,
     *     sold_item_name: string,
     *     sold_price: int,
     *     copper: int
     * }|null
     */
    public function sellCheapestInventoryItemByType(
        GameCharacter $character,
        string $type,
        ?string $subType = null,
    ): ?array {
        $equippedItemIds = $character->equipment()->pluck('item_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $items */
        $items = $character->items()
            ->where('is_in_storage', false)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->whereHas('definition', function ($query) use ($type, $subType) {
                $query->where('type', $type);
                if ($subType !== null) {
                    $query->where('sub_type', $subType);
                }
            })
            ->with('definition')
            ->get()
            ->filter(function ($item) use ($equippedItemIds): bool {
                if (! $item instanceof GameItem) {
                    return false;
                }

                return ! $equippedItemIds->contains($item->id);
            });

        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $items */
        if ($items->isEmpty()) {
            return null;
        }

        $this->ensureItemsSellPrice($items);

        /** @var GameItem|null $cheapest */
        $cheapest = $items
            ->sortBy(fn (GameItem $item) => $item->calculateSellPrice())
            ->first();

        if ($cheapest === null) {
            return null;
        }

        $soldName = $cheapest->definition->name ?? '物品';
        $soldId = $cheapest->id;
        $recycled = $this->sellSingleItem($character, $cheapest);

        return [
            'sold_item_id' => $soldId,
            'sold_item_name' => $soldName,
            'sold_price' => $recycled['total_price'],
            'copper' => $recycled['copper'],
        ];
    }

    /**
     * 拾取后尝试自动回收单个物品
     *
     * @return array{count: int, total_price: int, copper: int}|null
     */
    public function tryAutoRecycleItem(GameCharacter $character, GameItem $item): ?array
    {
        $maxValue = $character->auto_recycle_max_value;
        if ($maxValue === null || $maxValue <= 0) {
            return null;
        }

        if (! $this->shouldAutoRecycleItem($character, $item, $maxValue)) {
            return null;
        }

        return $this->sellSingleItem($character, $item);
    }

    /**
     * 批量出售背包中单价不超过指定价值的可售装备
     *
     * @return array{count: int, total_price: int, copper: int}
     */
    public function sellItemsAtOrBelowValue(GameCharacter $character, int $maxValue): array
    {
        $items = $this->getSellableInventoryItems($character)
            ->filter(fn (GameItem $item) => $item->calculateSellPrice() <= $maxValue);

        if ($items->isEmpty()) {
            return [
                'count' => 0,
                'total_price' => 0,
                'copper' => $character->copper,
            ];
        }

        return DB::transaction(function () use ($character, $items) {
            $totalPrice = 0;
            $count = 0;
            $equippedItemIds = $character->equipment()->pluck('item_id');

            foreach ($items as $item) {
                if ($equippedItemIds->contains($item->id)) {
                    continue;
                }

                $price = $item->calculateSellPrice() * $item->quantity;
                $totalPrice += $price;
                $count++;
                $item->delete();
            }

            $character->copper += $totalPrice;
            $character->save();
            $this->clearInventoryCache($character->id);

            return [
                'count' => $count,
                'total_price' => $totalPrice,
                'copper' => $character->copper,
            ];
        });
    }

    /**
     * 查找空槽位
     */
    public function findEmptySlot(GameCharacter $character, bool $inStorage): ?int
    {
        $maxSize = $inStorage ? self::STORAGE_SIZE : self::INVENTORY_SIZE;

        $usedSlots = $character->items()
            ->where('is_in_storage', $inStorage)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->whereNotNull('slot_index')
            ->pluck('slot_index')
            ->toArray();

        for ($i = 0; $i < $maxSize; $i++) {
            if (! in_array($i, $usedSlots)) {
                return $i;
            }
        }

        return null;
    }

    // ==================== 私有辅助方法 ====================

    /**
     * @param  Collection<int, GameItem>|\Illuminate\Database\Eloquent\Collection<int, GameItem>  $items
     */
    private function ensureItemsSellPrice(Collection|\Illuminate\Database\Eloquent\Collection $items): void
    {
        foreach ($items as $item) {
            $newPrice = $item->calculateSellPrice();
            if ($item->sell_price !== $newPrice) {
                $item->sell_price = $newPrice;
                $item->saveQuietly();
            }
        }
    }

    private function findItem(GameCharacter $character, int $itemId, bool $checkStorage = true): GameItem
    {
        $query = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            });

        if ($checkStorage) {
            $query->where('is_in_storage', false);
        }

        $item = $query->with('definition')->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        return $item;
    }

    private function checkStorageSpace(GameCharacter $character, bool $toStorage): void
    {
        if ($toStorage) {
            if ($character->isStorageFull()) {
                throw new \InvalidArgumentException('仓库已满');
            }
        } else {
            if ($character->isInventoryFull()) {
                throw new \InvalidArgumentException('背包已满');
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, GameItem>
     */
    private function sortItems(HasMany|Builder $query, string $sortBy): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $result */
        $result = match ($sortBy) {
            'quality' => $query->orderByDesc('quality')->orderBy('definition_id')->orderByDesc('quantity')->get(),
            'price' => $query->orderByDesc(\DB::raw('COALESCE(sell_price, 0) * quantity'))->orderBy('definition_id')->orderByDesc('quantity')->get(),
            default => $query->orderBy('id')->get(),
        };

        return $result;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, GameItem>
     */
    private function getSellableItemsByQuality(GameCharacter $character, string $quality): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $result */
        $result = $character->items()
            ->where('is_in_storage', false)
            ->where('quality', $quality)
            ->whereHas('definition', fn ($q) => $q->where('type', '!=', 'gem'))
            ->with('definition')
            ->get();

        return $result;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, GameItem>
     */
    private function getSellableInventoryItems(GameCharacter $character): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $result */
        $result = $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', fn ($q) => $q->where('type', '!=', 'gem'))
            ->with('definition')
            ->get();

        return $result;
    }

    private function shouldAutoRecycleItem(GameCharacter $character, GameItem $item, int $maxValue): bool
    {
        if ($item->is_in_storage || $item->is_equipped) {
            return false;
        }

        if ($this->equipmentHelper->isItemEquipped($character, $item->id)) {
            return false;
        }

        $type = $item->definition?->type;
        if ($type === 'gem') {
            return false;
        }

        return $item->calculateSellPrice() <= $maxValue;
    }

    /**
     * @return array{count: int, total_price: int, copper: int}
     */
    private function sellSingleItem(GameCharacter $character, GameItem $item): array
    {
        return DB::transaction(function () use ($character, $item) {
            $price = $item->calculateSellPrice() * $item->quantity;
            $item->delete();

            $character->copper += $price;
            $character->save();
            $this->clearInventoryCache($character->id);

            return [
                'count' => 1,
                'total_price' => $price,
                'copper' => $character->copper,
            ];
        });
    }

    private function clearInventoryCache(int $characterId): void
    {
        Cache::forget(self::CACHE_PREFIX . $characterId);
    }
}
