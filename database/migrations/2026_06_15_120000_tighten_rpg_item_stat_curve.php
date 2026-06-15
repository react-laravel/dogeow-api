<?php

use App\Models\Game\GameCharacter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tighten generated RPG item stats to match the current small-number combat curve.
     */
    public function up(): void
    {
        $this->scaleItemDefinitions();
        $this->scaleExistingItems();
        $this->clampCharacterResources();
    }

    public function down(): void
    {
        // Intentionally irreversible: existing generated items cannot be scaled back
        // without compounding random rolls and already-equipped gem bonuses.
    }

    private function scaleItemDefinitions(): void
    {
        DB::table('game_item_definitions')
            ->where('type', '!=', 'potion')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $definition): void {
                $updates = [];

                $baseStats = $this->decodeJsonObject($definition->base_stats ?? null);
                if ($baseStats !== []) {
                    $updates['base_stats'] = $this->encodeJson($this->scaleStats($baseStats));
                }

                $gemStats = $this->decodeJsonObject($definition->gem_stats ?? null);
                if ($gemStats !== []) {
                    $updates['gem_stats'] = $this->encodeJson($this->scaleStats($gemStats));
                }

                if ($updates !== []) {
                    DB::table('game_item_definitions')->where('id', $definition->id)->update($updates);
                }
            });
    }

    private function scaleExistingItems(): void
    {
        DB::table('game_items')
            ->leftJoin('game_item_definitions', 'game_items.definition_id', '=', 'game_item_definitions.id')
            ->where(function ($query): void {
                $query->whereNull('game_item_definitions.type')
                    ->orWhere('game_item_definitions.type', '!=', 'potion');
            })
            ->orderBy('game_items.id')
            ->select([
                'game_items.id',
                'game_items.stats',
                'game_items.affixes',
            ])
            ->lazyById(column: 'game_items.id', alias: 'id')
            ->each(function (object $item): void {
                $updates = [];

                $stats = $this->decodeJsonObject($item->stats ?? null);
                if ($stats !== []) {
                    $updates['stats'] = $this->encodeJson($this->scaleStats($stats));
                }

                $affixes = $this->decodeJsonArray($item->affixes ?? null);
                if ($affixes !== []) {
                    $updates['affixes'] = $this->encodeJson(array_map(
                        fn (array $affix): array => $this->scaleStats($affix),
                        $affixes
                    ));
                }

                if ($updates !== []) {
                    DB::table('game_items')->where('id', $item->id)->update($updates);
                }
            });
    }

    private function clampCharacterResources(): void
    {
        GameCharacter::query()
            ->with(['equipment.item.definition', 'equipment.item.gems.gemDefinition'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (GameCharacter $character): void {
                $maxHp = $character->getMaxHp();
                $maxMana = $character->getMaxMana();
                $updates = [];

                if ($character->current_hp !== null && $character->current_hp > $maxHp) {
                    $updates['current_hp'] = $maxHp;
                }

                if ($character->current_mana !== null && $character->current_mana > $maxMana) {
                    $updates['current_mana'] = $maxMana;
                }

                if ($updates !== []) {
                    DB::table('game_characters')->where('id', $character->id)->update($updates);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function scaleStats(array $stats): array
    {
        $intMultipliers = [
            'max_hp' => 0.25,
            'max_mana' => 0.25,
            'attack' => 0.50,
            'defense' => 0.50,
            'strength' => 0.50,
            'dexterity' => 0.50,
            'vitality' => 0.50,
            'energy' => 0.50,
            'all_stats' => 0.50,
        ];

        $floatMultipliers = [
            'crit_rate' => 0.70,
            'crit_damage' => 0.70,
        ];

        foreach ($stats as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            if (array_key_exists($key, $intMultipliers)) {
                $stats[$key] = $this->scaleInt($value, $intMultipliers[$key]);
            } elseif (array_key_exists($key, $floatMultipliers)) {
                $stats[$key] = max(0.0001, round((float) $value * $floatMultipliers[$key], 4));
            }
        }

        return $stats;
    }

    private function scaleInt(mixed $value, float $multiplier): int
    {
        $numeric = (float) $value;
        if ($numeric <= 0) {
            return 0;
        }

        return max(1, (int) round($numeric * $multiplier));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeJsonArray(mixed $value): array
    {
        $decoded = $this->decodeJsonObject($value);

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
