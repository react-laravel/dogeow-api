<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 背包服务类
 *
 * 负责背包相关的业务逻辑，包括物品装备、卸下、出售、移动等
 */
class GameInventoryService
{
    /** 背包默认大小 */
    public const INVENTORY_SIZE = 100;

    /** 仓库默认大小 */
    public const STORAGE_SIZE = 50;

    /** 缓存键前缀 */
    private const CACHE_PREFIX = 'game_inventory:';

    /** 缓存有效期（秒） */
    private const CACHE_TTL = 60;

     /**
      * 获取背包物品
      *
      * @param GameCharacter $character 角色实例
      * @return array{inventory: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem>, storage: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem>, equipment: \Illuminate\Support\Collection<string, \App\Models\Game\GameEquipment>, inventory_size: int, storage_size: int}
      */
    public function getInventory(GameCharacter $character): array
    {
        // 背包：不在仓库且未装备（兼容 is_equipped 为 null 或 false 的情况）
        $inventory = $character->items()
            ->where('is_in_storage', false)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->with(['definition', 'gems.gemDefinition'])
            ->orderBy('slot_index')
            ->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem> $inventory */

        // 仓库：未装备
        $storage = $character->items()
            ->where('is_in_storage', true)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            })
            ->with(['definition', 'gems.gemDefinition'])
            ->orderBy('slot_index')
            ->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem> $storage */

        $equipment = $character->equipment()
            ->with(['item.definition', 'item.gems.gemDefinition'])
            ->get()
            ->keyBy('slot');
        /** @var \Illuminate\Support\Collection<string, \App\Models\Game\GameEquipment> $equipment */

        $this->ensureItemsSellPrice($inventory);
        $this->ensureItemsSellPrice($storage);
        foreach ($equipment as $eq) {
            /** @var \App\Models\Game\GameEquipment $eq */
            if (isset($eq->item) && $eq->item instanceof GameItem) {
                $this->ensureItemsSellPrice(collect([$eq->item]));
            }
        }

        return [
            'inventory' => $inventory,
            'storage' => $storage,
            'equipment' => $equipment,
            'inventory_size' => self::INVENTORY_SIZE,
            'storage_size' => self::STORAGE_SIZE,
        ];
    }

        /**
         * 确保物品列表中的 sell_price 已计算（若为 0 或未设置则按属性计算）
         *
         * @param \Illuminate\Support\Collection<int, \App\Models\Game\GameItem>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem> $items
         */
        private function ensureItemsSellPrice(\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $items): void
    {
        /** @var GameItem $item */
        foreach ($items as $item) {
            if (! isset($item->sell_price) || $item->sell_price === 0) {
                $item->sell_price = $item->calculateSellPrice();
                $item->saveQuietly();
            }
        }
    }

     /**
      * 获取背包数据（用于 WebSocket 广播）
      *
    * @param GameCharacter $character 角色实例
    * @return array{inventory: array<int, array<int|string,mixed>>, storage: array<int, array<int|string,mixed>>, equipment: array<string, array<int|string,mixed>|null>, inventory_size: int, storage_size: int}
      */
    public function getInventoryForBroadcast(GameCharacter $character): array
    {
        $result = $this->getInventory($character);
        $equipmentArray = [];

        /** @var \Illuminate\Support\Collection<string, \App\Models\Game\GameEquipment> $resultEquipment */
        $resultEquipment = $result['equipment'];
        foreach ($resultEquipment as $slot => $eq) {
            /** @var \App\Models\Game\GameEquipment $eq */
            $equipmentArray[$slot] = isset($eq->item) && $eq->item ? $eq->item->toArray() : null;
        }

        $inventoryArr = array_values($result['inventory']->toArray());
        /** @var array<int, array<int|string,mixed>> $inventoryArr */

        $storageArr = array_values($result['storage']->toArray());
        /** @var array<int, array<int|string,mixed>> $storageArr */

        return [
            'inventory' => $inventoryArr,
            'storage' => $storageArr,
            'equipment' => $equipmentArray,
            'inventory_size' => $result['inventory_size'],
            'storage_size' => $result['storage_size'],
        ];
    }

    /**
     * 装备物品
     *
     * @param  GameCharacter  $character  角色实例
     * @param  int  $itemId  物品ID
     * @return array 装备结果
     *
     * @throws \InvalidArgumentException 物品不存在或无法装备
     */
    /**
     * @return array{equipped_item: \App\Models\Game\GameItem, equipped_slot: string, unequipped_item: \App\Models\Game\GameItem|null, combat_stats: array<string,mixed>, stats_breakdown: array<string,mixed>}
     */
    public function equipItem(GameCharacter $character, int $itemId): array
    {
        $item = $this->findItem($character, $itemId, false);

        // 检查是否可以装备
        $canEquip = $item->canEquip($character);
        if (! ($canEquip['can_equip'] ?? false)) {
            $reason = $canEquip['reason'] ?? '无法装备';
            if (! is_string($reason)) {
                if (is_scalar($reason) || $reason === null) {
                    $reason = strval($reason);
                } else {
                    $encoded = @json_encode($reason);
                    $reason = is_string($encoded) ? $encoded : '无法装备';
                }
            }
            throw new \InvalidArgumentException($reason);
        }

        // 确定装备槽位
        $slot = $this->determineEquipmentSlot($character, $item);

        return DB::transaction(function () use ($character, $item, $slot) {
            $equipmentSlot = $this->getOrCreateEquipmentSlot($character, $slot);

            // 如果槽位已有装备，先卸下
            $oldItem = $this->handleUnequipIfNeeded($character, $equipmentSlot);

            // 装备新物品
            $equipmentSlot->item_id = $item->id;
            $equipmentSlot->save();

            // 标记为已装备
            $item->is_equipped = true;
            $item->slot_index = null;
            $item->save();

            $character->refresh();

            // 构造卸下项以避免在返回时对可能为 null 的对象直接调用方法
            $unequipped = null;
            if ($oldItem instanceof GameItem) {
                $unequipped = $oldItem->load('definition');
            }

            // 清除缓存
            $this->clearInventoryCache($character->id);

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
     * @param  GameCharacter  $character  角色实例
     * @param  string  $slot  装备槽位
     * @return array 卸下结果
     *
     * @throws \InvalidArgumentException 槽位没有装备或背包已满
     */
    /**
     * @return array{item: \App\Models\Game\GameItem|null, combat_stats: array<string,mixed>, stats_breakdown: array<string,mixed>}
     */
    public function unequipItem(GameCharacter $character, string $slot): array
    {
        /** @var \App\Models\Game\GameEquipment|null $equipmentSlot */
        $equipmentSlot = $character->equipment()->where('slot', $slot)->first();

        if (! $equipmentSlot || ! $equipmentSlot->item_id) {
            throw new \InvalidArgumentException('该槽位没有装备');
        }

        // 检查背包空间
        $emptySlot = $this->findEmptySlot($character, false);
        if ($emptySlot === null) {
            throw new \InvalidArgumentException('背包已满');
        }

        /** @var \App\Models\Game\GameEquipment $equipmentSlot */
        return DB::transaction(function () use ($character, $equipmentSlot, $emptySlot) {
            /** @var int|null $itemId */
            $itemId = $equipmentSlot->item_id;
            $item = $itemId ? GameItem::with('definition')->find($itemId) : null;

            // 卸下装备到背包
            if ($item instanceof GameItem) {
                $item->is_equipped = false;
                $item->slot_index = $emptySlot;
                $item->save();
            }

            $equipmentSlot->item_id = null;
            $equipmentSlot->save();

            $character->refresh();

            // 清除缓存
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
     * @param  GameCharacter  $character  角色实例
     * @param  int  $itemId  物品ID
     * @param  int  $quantity  数量
    * @return array{copper:int, sell_price:int}
     *
     * @throws \InvalidArgumentException 物品不存在或数量不足
     */
    public function sellItem(GameCharacter $character, int $itemId, int $quantity = 1): array
    {
        $item = $this->findItem($character, $itemId);

        // 检查装备中的物品
        if ($this->isItemEquipped($character, $itemId)) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            throw new \InvalidArgumentException('物品数量不足');
        }

        // 计算售价
        $sellPrice = $item->calculateSellPrice() * $quantity;

        return DB::transaction(function () use ($character, $item, $quantity, $sellPrice) {
            // 更新铜币
            $character->copper += $sellPrice;
            $character->save();

            // 减少或删除物品
            if ($item->quantity > $quantity) {
                $item->quantity -= $quantity;
                $item->save();
            } else {
                $item->delete();
            }

            // 清除缓存
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
     * @param  GameCharacter  $character  角色实例
     * @param  int  $itemId  物品ID
     * @param  bool  $toStorage  是否移动到仓库
     * @param  int|null  $slotIndex  指定槽位（可选）
     * @return array{item: \App\Models\Game\GameItem} 移动结果
     *
     * @throws \InvalidArgumentException 目标位置已满
     */
    public function moveItem(GameCharacter $character, int $itemId, bool $toStorage, ?int $slotIndex = null): array
    {
        $item = $this->findItem($character, $itemId);

        // 检查目标空间
        $this->checkStorageSpace($character, $toStorage);

        $item->is_in_storage = $toStorage;
        $item->slot_index = $slotIndex ?? $this->findEmptySlot($character, $toStorage);
        $item->save();

        // 清除缓存
        $this->clearInventoryCache($character->id);

        /**
         * @return array{item: \App\Models\Game\GameItem}
         */
        return ['item' => $item];
    }

    /**
     * 使用药品
     *
     * @param  GameCharacter  $character  角色实例
     * @param  int  $itemId  物品ID
     * @return array 使用结果
     *
     * @throws \InvalidArgumentException 物品不存在或不是药品
     */
    /**
     * @return array{character: GameCharacter, combat_stats: array<string,mixed>, current_hp: int, current_mana: int, message: string}
     */
    public function usePotion(GameCharacter $character, int $itemId): array
    {
        $item = $this->findItem($character, $itemId, false);

        $definition = $item->definition;
        /** @var \App\Models\Game\GameItemDefinition $definition */
        // 检查是否是药品
        if (! $definition || $definition->type !== 'potion') {
            throw new \InvalidArgumentException('该物品不是药品');
        }

        // 检查是否已装备
        if ($this->isItemEquipped($character, $itemId)) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        // 获取药品效果
        $effects = $this->getPotionEffects($item);

        $definitionName = isset($definition->name) && is_string($definition->name) ? $definition->name : '物品';
        $itemDbId = (int) $item->getRawOriginal('id');
        $quantity = (int) $item->quantity;

        $affected = 0;
        DB::transaction(function () use ($character, $itemDbId, $quantity, $effects, &$affected) {
            // 恢复HP/Mana
            if ($effects['hp'] > 0) {
                $character->restoreHp($effects['hp']);
            }
            if ($effects['mana'] > 0) {
                $character->restoreMana($effects['mana']);
            }

            // 扣减数量或删除
            $query = DB::table('game_items')
                ->where('id', $itemDbId)
                ->where('character_id', $character->id);

            if ($quantity > 1) {
                $affected = $query->decrement('quantity', 1);
            } else {
                $affected = $query->delete();
            }
        });

        if ($affected === 0) {
            throw new \RuntimeException('消耗药品失败，请重试');
        }

        $character->refresh();

        // 构建恢复消息
        $restoreMessage = $this->formatRestoreMessage($effects);
        // 保证返回字符串非空，避免 phpstan 将其识别为假值
        $restoreMessage = $restoreMessage ?: '0 点';

        return [
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'message' => "使用{$definitionName}成功，恢复了 {$restoreMessage}",
        ];
    }

    /**
     * 整理背包
     *
     * @param  GameCharacter  $character  角色实例
     * @param  string  $sortBy  排序方式: quality, price, default
     * @return array 整理结果
     */
    /**
     * @return array{inventory: \Illuminate\Support\Collection<int, GameItem>}
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

        $slotIndex = 0;
        foreach ($items as $item) {
            $item->slot_index = $slotIndex++;
            $item->save();
        }

        // 清除缓存
        $this->clearInventoryCache($character->id);

        return ['inventory' => $items];
    }

    /**
     * 批量出售指定品质的物品
     *
     * @param  GameCharacter  $character  角色实例
     * @param  string  $quality  品质
     * @return array 出售结果
     */
    /**
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

            foreach ($items as $item) {
                // 检查是否已装备
                if ($this->isItemEquipped($character, $item->id)) {
                    continue;
                }

                $price = $item->calculateSellPrice() * $item->quantity;
                $totalPrice += $price;
                $count++;

                $item->delete();
            }

            $character->copper += $totalPrice;
            $character->save();

            // 清除缓存
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
     *
     * @param  GameCharacter  $character  角色实例
     * @param  bool  $inStorage  是否在仓库中
     * @return int|null 空槽位索引
     */
    public function findEmptySlot(GameCharacter $character, bool $inStorage): ?int
    {
        $maxSize = $inStorage ? self::STORAGE_SIZE : self::INVENTORY_SIZE;

        // 查询已使用的槽位时，排除已装备的物品
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
     * 查找物品
     */
    private function findItem(GameCharacter $character, int $itemId, bool $checkStorage = true): GameItem
    {
        $query = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->where(function ($query) {
                $query->where('is_equipped', false)->orWhereNull('is_equipped');
            }); // 排除已装备的物品

        if ($checkStorage) {
            $query->where('is_in_storage', false);
        }

        $item = $query->with('definition')->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        return $item;
    }

    /**
     * 确定装备槽位
     */
    private function determineEquipmentSlot(GameCharacter $character, GameItem $item): string
    {
        /** @var \App\Models\Game\GameItemDefinition|null $def */
        $def = $item->definition;
        if (! $def) {
            throw new \InvalidArgumentException('该物品没有定义，无法装备');
        }

        $slot = $def->getEquipmentSlot();
        if (! $slot) {
            throw new \InvalidArgumentException('该物品无法装备');
        }

        // 如果是戒指，检查两个戒指槽位
        if (($def->type ?? null) === 'ring') {
            $slot = $this->findAvailableRingSlot($character);
        }

        return $slot;
    }

    /**
     * 查找可用的戒指槽位
     */
    private function findAvailableRingSlot(GameCharacter $character): string
    {
        /** @var \App\Models\Game\GameEquipment|null $ring */
        $ring = $character->equipment()->where('slot', 'ring')->first();

        if ($ring && ! $ring->item_id) {
            return 'ring';
        }

        return 'ring';
    }

    /**
     * 获取或创建装备槽位
     */
    private function getOrCreateEquipmentSlot(GameCharacter $character, string $slot): \App\Models\Game\GameEquipment
    {
        $equipmentSlot = $character->equipment()->where('slot', $slot)->first();

        if (! $equipmentSlot) {
            $equipmentSlot = $character->equipment()->create(['slot' => $slot]);
            /** @var \App\Models\Game\GameEquipment $equipmentSlot */
        }

        /** @var \App\Models\Game\GameEquipment $equipmentSlot */
        return $equipmentSlot;
    }

    /**
     * 如果需要则卸下装备
     */
    private function handleUnequipIfNeeded(GameCharacter $character, \App\Models\Game\GameEquipment $equipmentSlot): ?GameItem
    {
        $oldItem = null;

        if ($equipmentSlot->item_id) {
            $oldItem = GameItem::find($equipmentSlot->item_id);
            if ($oldItem) {
                $oldItem->is_equipped = false;
                $oldItem->slot_index = $this->findEmptySlot($character, false);
                $oldItem->save();
            }
        }

        return $oldItem;
    }

    /**
     * 检查物品是否已装备
     */
    private function isItemEquipped(GameCharacter $character, int $itemId): bool
    {
        return $character->equipment()->where('item_id', $itemId)->exists();
    }

    /**
     * 检查存储空间
     */
    private function checkStorageSpace(GameCharacter $character, bool $toStorage): void
    {
        if ($toStorage) {
            $storageCount = $character->items()->where('is_in_storage', true)->count();
            if ($storageCount >= self::STORAGE_SIZE) {
                throw new \InvalidArgumentException('仓库已满');
            }
        } else {
            $inventoryCount = $character->items()->where('is_in_storage', false)->count();
            if ($inventoryCount >= self::INVENTORY_SIZE) {
                throw new \InvalidArgumentException('背包已满');
            }
        }
    }

    /**
     * 获取药品效果
     */
    /**
     * @return array{hp:int, mana:int}
     */
    private function getPotionEffects(GameItem $item): array
    {
        $baseStats = [];
        $def = $item->definition;
        if ($def && is_array($def->base_stats ?? null)) {
            /** @var array<string,mixed> $baseStats */
            $baseStats = (array) $def->base_stats;
        }

        $hp = 0;
        if (isset($baseStats['max_hp']) && is_numeric($baseStats['max_hp'])) {
            $hp = (int) $baseStats['max_hp'];
        } elseif (isset($baseStats['restore_amount']) && is_numeric($baseStats['restore_amount'])) {
            $hp = (int) $baseStats['restore_amount'];
        }

        $mana = 0;
        if (isset($baseStats['max_mana']) && is_numeric($baseStats['max_mana'])) {
            $mana = (int) $baseStats['max_mana'];
        }

        return [
            'hp' => $hp,
            'mana' => $mana,
        ];
    }

    /**
     * 格式化恢复消息
     */
    /**
     * @param array<string,int> $effects
     */
    /**
     * @param array<string,int> $effects
     */
    private function formatRestoreMessage(array $effects): string
    {
        $restoreText = [];

        $hp = (int) ($effects['hp'] ?? 0);
        $mana = (int) ($effects['mana'] ?? 0);

        if ($hp > 0) {
            $restoreText[] = "{$hp} 点生命值";
        }
        if ($mana > 0) {
            $restoreText[] = "{$mana} 点法力值";
        }

        return implode('和', $restoreText);
    }

    /**
     * 排序物品
     *
     * @param \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Game\GameItem, \App\Models\Game\GameCharacter>|\Illuminate\Database\Eloquent\Builder<\App\Models\Game\GameItem> $query
     * @param string $sortBy
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem>
     */
    private function sortItems(\Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Builder $query, string $sortBy): \Illuminate\Database\Eloquent\Collection
    {
        return match ($sortBy) {
            'quality' => $query->orderByDesc('quality')
                ->orderBy('definition_id')
                ->orderByDesc('quantity')
                ->get(),
            'price' => $query->orderByDesc(\DB::raw('COALESCE(sell_price, 0) * quantity'))
                ->orderBy('definition_id')
                ->orderByDesc('quantity')
                ->get(),
            default => $query->orderBy('definition_id')
                ->orderByDesc('quality')
                ->orderByDesc('quantity')
                ->get(),
        };
    }

    /**
     * 获取可出售的物品（按品质）
     */
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, GameItem>
     */
    private function getSellableItemsByQuality(GameCharacter $character, string $quality): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, GameItem> $items */
        $items = $character->items()
            ->where('is_in_storage', false)
            ->where('quality', $quality)
            ->whereHas('definition', function ($query) {
                $query->whereNotIn('type', ['potion', 'gem']);
            })
            ->with('definition')
            ->get();

        return $items;
    }

    /**
     * 清除背包缓存
     */
    private function clearInventoryCache(int $characterId): void
    {
        Cache::forget(self::CACHE_PREFIX . $characterId);
    }
}
