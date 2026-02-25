<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;

class GamePotionService
{
    /**
     * Try to automatically use potions based on HP/MANA thresholds
     *
     * @param array<string,mixed> $charStats
     * @return array<string,array<string,mixed>> List of potions used
     */
    public function tryAutoUsePotions(GameCharacter $character, int $currentHp, int $currentMana, array $charStats): array
    {
        $used = [];

        $hpThreshold = (int) ($character->hp_potion_threshold ?? 30);
        $hpThreshold = max(1, min(100, $hpThreshold));
        if ($character->auto_use_hp_potion) {
            $maxHp = (int) ($charStats['max_hp'] ?? 0);
            if ($maxHp > 0) {
                $hpPercent = ($currentHp / $maxHp) * 100;
            } else {
                $hpPercent = 100;
            }
            if ($hpPercent <= $hpThreshold) {
                $potion = $this->findBestPotion($character, 'hp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $def = $potion->definition;
                        /** @var GameItemDefinition|null $def */
                        $base = [];
                        if ($def instanceof GameItemDefinition) {
                            $base = $def->getBaseStats();
                        }
                    $used['hp'] = [
                        'name' => isset($def->name) && is_string($def->name) ? $def->name : '药品',
                        'restored' => (int) ($base['max_hp'] ?? 0),
                    ];
                }
            }
        }

        $mpThreshold = (int) ($character->mp_potion_threshold ?? 30);
        $mpThreshold = max(1, min(100, $mpThreshold));
        if ($character->auto_use_mp_potion) {
            $maxMana = (int) ($charStats['max_mana'] ?? 0);
            if ($maxMana > 0) {
                $mpPercent = ($currentMana / $maxMana) * 100;
            } else {
                $mpPercent = 100;
            }
            if ($mpPercent <= $mpThreshold) {
                $potion = $this->findBestPotion($character, 'mp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $def = $potion->definition;
                        /** @var GameItemDefinition|null $def */
                        $base = [];
                        if ($def instanceof GameItemDefinition) {
                            $base = $def->getBaseStats();
                        }
                    $used['mp'] = [
                        'name' => isset($def->name) && is_string($def->name) ? $def->name : '药品',
                        'restored' => (int) ($base['max_mana'] ?? 0),
                    ];
                }
            }
        }

        return $used;
    }

    /**
     * Find the best potion for the given type
     */
    public function findBestPotion(GameCharacter $character, string $type): ?GameItem
    {
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

        $collection = $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                    ->where('sub_type', $type);
            })
            ->with('definition')
            ->get();

        /** @var GameItem|null $first */
        $first = $collection->sortByDesc(fn ($item) => (int) ((isset($item->definition) && is_array($item->definition->base_stats ?? null)) ? ($item->definition->base_stats[$statKey] ?? 0) : 0))->first();

        return $first;
    }

    /**
     * Use a potion item
     */
    public function usePotionItem(GameCharacter $character, GameItem $potion): void
    {
        $stats = [];
        $def = $potion->definition;
        if ($def && is_array($def->base_stats ?? null)) {
            $stats = (array) $def->base_stats;
        }
        $hpRestored = (int) ($stats['max_hp'] ?? 0);
        $manaRestored = (int) ($stats['max_mana'] ?? 0);

        if ($hpRestored > 0) {
            $character->restoreHp($hpRestored);
        }
        if ($manaRestored > 0) {
            $character->restoreMana($manaRestored);
        }

        $potion->quantity > 1 ? $potion->decrement('quantity') : $potion->delete();
    }

    /**
     * Check if character has any potions of a specific type
     */
    public function hasPotion(GameCharacter $character, string $type): bool
    {
        return $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                    ->where('sub_type', $type);
            })
            ->exists();
    }

    /**
     * Get potion inventory count
     */
    public function getPotionCount(GameCharacter $character, ?string $type = null): int
    {
        $query = $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion');
                if ($type !== null) {
                    $query->where('sub_type', $type);
                }
            });

        return $query->count();
    }

    /**
     * Get all potions in inventory
     */
    /**
     * @return array<int,array{id:int,type:string,name:string,quantity:int,restore_hp:int,restore_mp:int}>
     */
    public function getAllPotions(GameCharacter $character): array
    {
        $potions = $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) {
                $query->where('type', 'potion');
            })
            ->with('definition')
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Game\GameItem> $potions */
        return $potions->map(function (GameItem $potion): array {
            $def = $potion->definition;
            /** @var \App\Models\Game\GameItemDefinition|null $def */
            $base = [];
            if ($def && is_array($def->base_stats ?? null)) {
                $base = (array) $def->base_stats;
            }

            return [
                'id' => $potion->id,
                'type' => isset($def->sub_type) && is_string($def->sub_type) ? $def->sub_type : '',
                'name' => isset($def->name) && is_string($def->name) ? $def->name : '',
                'quantity' => (int) $potion->quantity,
                'restore_hp' => (int) ($base['max_hp'] ?? 0),
                'restore_mp' => (int) ($base['max_mana'] ?? 0),
            ];
        })->toArray();
    }
}
