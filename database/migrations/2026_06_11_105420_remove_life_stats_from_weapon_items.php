<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove max_hp from weapon definitions and existing weapon item stats/affixes.
     */
    public function up(): void
    {
        DB::table('game_item_definitions')
            ->where('type', 'weapon')
            ->whereNotNull('base_stats')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $definition): void {
                $stats = $this->decodeJsonObject($definition->base_stats);
                if (! array_key_exists('max_hp', $stats)) {
                    return;
                }

                unset($stats['max_hp']);

                DB::table('game_item_definitions')
                    ->where('id', $definition->id)
                    ->update(['base_stats' => $this->encodeJson($stats)]);
            });

        $weaponDefinitionIds = DB::table('game_item_definitions')
            ->where('type', 'weapon')
            ->pluck('id');

        DB::table('game_items')
            ->whereIn('definition_id', $weaponDefinitionIds)
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $item): void {
                $updates = [];

                $stats = $this->decodeJsonObject($item->stats);
                if (array_key_exists('max_hp', $stats)) {
                    unset($stats['max_hp']);
                    $updates['stats'] = $this->encodeJson($stats);
                }

                $affixes = $this->decodeJsonList($item->affixes);
                $cleanAffixes = [];
                $affixesChanged = false;
                foreach ($affixes as $affix) {
                    if (is_array($affix) && array_key_exists('max_hp', $affix)) {
                        unset($affix['max_hp']);
                        $affixesChanged = true;
                    }
                    $cleanAffixes[] = $affix;
                }

                if ($affixesChanged) {
                    $updates['affixes'] = $this->encodeJson($cleanAffixes);
                }

                if ($updates !== []) {
                    DB::table('game_items')
                        ->where('id', $item->id)
                        ->update($updates);
                }
            });
    }

    /**
     * The removed values are obsolete balance data and cannot be reconstructed safely.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
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
     * @return array<int, mixed>
     */
    private function decodeJsonList(mixed $value): array
    {
        $decoded = $this->decodeJsonObject($value);

        return array_is_list($decoded) ? $decoded : [];
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
