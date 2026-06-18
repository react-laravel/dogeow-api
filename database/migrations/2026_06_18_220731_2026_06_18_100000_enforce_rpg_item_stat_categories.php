<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Defense item types - only defense stats (defense, max_hp, max_mana) allowed
     */
    private function getDefenseTypes(): array
    {
        return ['helmet', 'armor', 'gloves', 'boots', 'belt'];
    }

    /**
     * Offense item types - only attack stats (attack, crit_rate, crit_damage, strength, dexterity, energy) allowed
     */
    private function getOffenseTypes(): array
    {
        return ['weapon', 'ring', 'amulet'];
    }

    /**
     * Get allowed stats for an item type (keys are stat names, values are original indices)
     *
     * @return array<string, int>
     */
    private function getAllowedStats(string $type): array
    {
        $defenseStats = ['defense', 'max_hp', 'max_mana'];
        $offenseStats = ['attack', 'crit_rate', 'crit_damage', 'strength', 'dexterity', 'energy'];

        if (in_array($type, $this->getDefenseTypes(), true)) {
            return array_flip($defenseStats);
        }

        if (in_array($type, $this->getOffenseTypes(), true)) {
            return array_flip($offenseStats);
        }

        return [];
    }

    /**
     * Filter stats array to only include allowed keys
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function filterStats(array $stats, array $allowed): array
    {
        if ($stats === [] || $allowed === []) {
            return $stats;
        }

        return array_filter($stats, fn (string $key): bool => isset($allowed[$key]), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Filter affixes array to only include allowed keys in each affix
     *
     * @param  array<string|int, mixed>  $affixes
     * @return array<string|int, mixed>
     */
    private function filterAffixes(array $affixes, array $allowed): array
    {
        if ($affixes === [] || $allowed === []) {
            return $affixes;
        }

        $filtered = [];
        foreach ($affixes as $affix) {
            if (! is_array($affix)) {
                $filtered[] = $affix;

                continue;
            }
            $filtered[] = $this->filterStats($affix, $allowed);
        }

        return $filtered;
    }

    /**
     * Decode JSON value to array
     *
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode array to JSON
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Fix game_item_definitions.base_stats
        DB::table('game_item_definitions')
            ->whereIn('type', array_merge($this->getDefenseTypes(), $this->getOffenseTypes()))
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $definition): void {
                $allowed = $this->getAllowedStats($definition->type);
                if ($allowed === []) {
                    return;
                }

                $baseStats = $this->decodeJson($definition->base_stats ?? null);
                $filtered = $this->filterStats($baseStats, $allowed);

                if ($filtered !== $baseStats) {
                    DB::table('game_item_definitions')
                        ->where('id', $definition->id)
                        ->update(['base_stats' => $this->encodeJson($filtered)]);
                }
            });

        // Step 2: Fix game_items.stats and game_items.affixes
        DB::table('game_items')
            ->join('game_item_definitions', 'game_items.definition_id', '=', 'game_item_definitions.id')
            ->whereIn('game_item_definitions.type', array_merge($this->getDefenseTypes(), $this->getOffenseTypes()))
            ->select([
                'game_items.id',
                'game_items.stats',
                'game_items.affixes',
                'game_item_definitions.type',
            ])
            ->orderBy('game_items.id')
            ->lazyById(column: 'game_items.id', alias: 'id')
            ->each(function (object $item): void {
                $allowed = $this->getAllowedStats($item->type);
                if ($allowed === []) {
                    return;
                }

                $updates = [];

                $stats = $this->decodeJson($item->stats ?? null);
                $filteredStats = $this->filterStats($stats, $allowed);
                if ($filteredStats !== $stats) {
                    $updates['stats'] = $this->encodeJson($filteredStats);
                }

                $affixes = $this->decodeJson($item->affixes ?? null);
                $filteredAffixes = $this->filterAffixes($affixes, $allowed);
                if ($filteredAffixes !== $affixes) {
                    $updates['affixes'] = $this->encodeJson($filteredAffixes);
                }

                if ($updates !== []) {
                    DB::table('game_items')
                        ->where('id', $item->id)
                        ->update($updates);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * The removed stats are obsolete balance data and cannot be reconstructed safely.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }
};
