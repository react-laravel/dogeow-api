<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Collection;

class GameCombatLogService
{
    /**
     * Create a combat log entry for a round
     */
    public function createRoundLog(
        GameCharacter $character,
        GameMapDefinition $map,
        int $monsterId,
        array $roundResult,
        ?array $potionUsedBeforeRound = null,
        ?array $potionUsedAfterRound = null
    ): GameCombatLog {
        $roundDetails = $roundResult['round_details'] ?? [];

        return GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monsterId,
            'damage_dealt' => $roundResult['round_damage_dealt'],
            'damage_taken' => $roundResult['round_damage_taken'],
            'victory' => $roundResult['victory'] ?? false,
            'loot_dropped' => ! empty($roundResult['loot']) ? $roundResult['loot'] : null,
            'experience_gained' => $roundResult['experience_gained'] ?? 0,
            'copper_gained' => $roundResult['copper_gained'] ?? 0,
            'duration_seconds' => 0,
            'skills_used' => $roundResult['skills_used_this_round'],
            'potion_used' => [
                'before' => $potionUsedBeforeRound ?: null,
                'after' => $potionUsedAfterRound ?: null,
            ],
            // 角色属性
            'character_level' => $roundDetails['character']['level'] ?? $character->level,
            'character_class' => $roundDetails['character']['class'] ?? $character->class,
            'character_attack' => $roundDetails['character']['attack'] ?? null,
            'character_defense' => $roundDetails['character']['defense'] ?? null,
            'character_crit_rate' => $roundDetails['character']['crit_rate'] ?? null,
            'character_crit_damage' => $roundDetails['character']['crit_damage'] ?? null,
            // 怪物属性
            'monster_level' => $roundDetails['monster']['level'] ?? null,
            'monster_hp' => $roundDetails['monster']['hp'] ?? null,
            'monster_max_hp' => $roundDetails['monster']['max_hp'] ?? null,
            'monster_attack' => $roundDetails['monster']['attack'] ?? null,
            'monster_defense' => $roundDetails['monster']['defense'] ?? null,
            'monster_experience' => $roundDetails['monster']['experience'] ?? null,
            // 伤害详情
            'base_attack_damage' => $roundDetails['damage']['base_attack'] ?? null,
            'skill_damage' => $roundDetails['damage']['skill_damage'] ?? null,
            'crit_damage' => $roundDetails['damage']['crit_damage'] ?? null,
            'aoe_damage' => $roundDetails['damage']['aoe_damage'] ?? null,
            'total_damage_to_monsters' => $roundDetails['damage']['total'] ?? null,
            'monster_defense_reduction' => $roundDetails['damage']['defense_reduction'] ?? null,
            'monster_counter_damage' => $roundDetails['damage']['monster_counter'] ?? null,
            // 战斗详情
            'round_number' => $roundDetails['battle']['round'] ?? null,
            'monsters_alive_count' => $roundDetails['battle']['alive_count'] ?? null,
            'monsters_killed_count' => $roundDetails['battle']['killed_count'] ?? null,
            // 难度相关
            'difficulty_tier' => $roundDetails['difficulty']['tier'] ?? null,
            'difficulty_multiplier' => $roundDetails['difficulty']['multiplier'] ?? null,
        ]);
    }

    /**
     * Create a combat log entry for defeat
     */
    public function createDefeatLog(
        GameCharacter $character,
        GameMapDefinition $map,
        GameMonsterDefinition $monster,
        array $roundResult,
        int $currentRound
    ): GameCombatLog {
        $startTime = $character->combat_started_at ?? now();
        $charStats = $character->getCombatStats();
        $difficulty = $character->getDifficultyMultipliers();

        return GameCombatLog::create([
            'character_id' => $character->id,
            'map_id' => $map->id,
            'monster_id' => $monster->id,
            'damage_dealt' => $character->combat_total_damage_dealt,
            'damage_taken' => $character->combat_total_damage_taken,
            'victory' => false,
            'loot_dropped' => null,
            'experience_gained' => 0,
            'copper_gained' => 0,
            'duration_seconds' => $startTime->diffInSeconds(now()),
            'skills_used' => $roundResult['new_skills_aggregated'],
            // 角色属性
            'character_level' => $character->level,
            'character_class' => $character->class,
            'character_attack' => $charStats['attack'] ?? null,
            'character_defense' => $charStats['defense'] ?? null,
            'character_crit_rate' => $charStats['crit_rate'] ?? null,
            'character_crit_damage' => $charStats['crit_damage'] ?? null,
            // 怪物属性
            'monster_level' => $roundResult['monster']['level'] ?? $monster->level ?? null,
            'monster_hp' => $roundResult['monster']['hp'] ?? null,
            'monster_max_hp' => $roundResult['monster']['max_hp'] ?? null,
            'monster_attack' => $roundResult['monster']['attack'] ?? $monster->attack_base ?? null,
            'monster_defense' => $roundResult['monster']['defense'] ?? $monster->defense_base ?? null,
            'monster_experience' => $roundResult['monster']['experience'] ?? $monster->experience_base ?? null,
            // 战斗详情
            'round_number' => $currentRound,
            // 难度相关
            'difficulty_tier' => $character->difficulty_tier ?? 0,
            'difficulty_multiplier' => $difficulty['reward'],
        ]);
    }

    /**
     * Get combat logs for a character
     */
    public function getCombatLogs(GameCharacter $character): array
    {
        $logs = $character->combatLogs()
            ->with(['monster', 'map'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ['logs' => $logs];
    }

    /**
     * Get single combat log detail
     */
    public function getCombatLogDetail(GameCharacter $character, int $logId): array
    {
        $log = $character->combatLogs()
            ->with(['monster', 'map'])
            ->where('id', $logId)
            ->first();

        if (! $log) {
            return ['error' => '日志不存在'];
        }

        // 格式化返回数据，包含所有详情
        return [
            'log' => [
                'id' => $log->id,
                'map' => [
                    'id' => $log->map_id,
                    'name' => $log->map?->name,
                ],
                'monster' => [
                    'id' => $log->monster_id,
                    'name' => $log->monster?->name,
                ],
                'victory' => $log->victory,
                'damage_dealt' => $log->damage_dealt,
                'damage_taken' => $log->damage_taken,
                'experience_gained' => $log->experience_gained,
                'copper_gained' => $log->copper_gained,
                'duration_seconds' => $log->duration_seconds,
                'skills_used' => $log->skills_used,
                'loot_dropped' => $log->loot_dropped,
                'potion_used' => $log->potion_used,
                'created_at' => $log->created_at->toISOString(),
                // 角色属性
                'character' => [
                    'level' => $log->character_level,
                    'class' => $log->character_class,
                    'attack' => $log->character_attack,
                    'defense' => $log->character_defense,
                    'crit_rate' => $log->character_crit_rate,
                    'crit_damage' => $log->character_crit_damage,
                ],
                // 怪物属性
                'monster_stats' => [
                    'level' => $log->monster_level,
                    'hp' => $log->monster_hp,
                    'max_hp' => $log->monster_max_hp,
                    'attack' => $log->monster_attack,
                    'defense' => $log->monster_defense,
                    'experience' => $log->monster_experience,
                    'copper' => $log->monster_copper,
                ],
                // 伤害详情
                'damage_detail' => [
                    'base_attack' => $log->base_attack_damage,
                    'skill_damage' => $log->skill_damage,
                    'crit_damage' => $log->crit_damage,
                    'aoe_damage' => $log->aoe_damage,
                    'total' => $log->total_damage_to_monsters,
                    'defense_reduction' => $log->monster_defense_reduction,
                    'counter_damage' => $log->monster_counter_damage,
                ],
                // 战斗详情
                'battle' => [
                    'round' => $log->round_number,
                    'alive_count' => $log->monsters_alive_count,
                    'killed_count' => $log->monsters_killed_count,
                ],
                // 难度
                'difficulty' => [
                    'tier' => $log->difficulty_tier,
                    'multiplier' => $log->difficulty_multiplier,
                ],
            ],
        ];
    }

    /**
     * Get combat statistics for a character
     */
    public function getCombatStats(GameCharacter $character): array
    {
        $combatLogs = $character->combatLogs();

        return [
            'stats' => [
                'total_battles' => $combatLogs->count(),
                'total_victories' => (clone $combatLogs)->where('victory', true)->count(),
                'total_defeats' => (clone $combatLogs)->where('victory', false)->count(),
                'total_damage_dealt' => $combatLogs->sum('damage_dealt'),
                'total_damage_taken' => $combatLogs->sum('damage_taken'),
                'total_experience_gained' => $combatLogs->sum('experience_gained'),
                'total_copper_gained' => $combatLogs->sum('copper_gained'),
                'total_items_looted' => (clone $combatLogs)->whereNotNull('loot_dropped')->count(),
            ],
        ];
    }

    /**
     * Format logs for API response
     */
    public function formatLogsForResponse(Collection $logs): array
    {
        return $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'monster' => $log->monster?->name,
                'map' => $log->map?->name,
                'damage_dealt' => $log->damage_dealt,
                'damage_taken' => $log->damage_taken,
                'victory' => $log->victory,
                'experience_gained' => $log->experience_gained,
                'copper_gained' => $log->copper_gained,
                'loot_dropped' => $log->loot_dropped,
                'duration_seconds' => $log->duration_seconds,
                'created_at' => $log->created_at->toISOString(),
                // 角色属性
                'character_level' => $log->character_level,
                'character_class' => $log->character_class,
                'character_attack' => $log->character_attack,
                'character_defense' => $log->character_defense,
                'character_crit_rate' => $log->character_crit_rate,
                'character_crit_damage' => $log->character_crit_damage,
                // 怪物属性
                'monster_level' => $log->monster_level,
                'monster_hp' => $log->monster_hp,
                'monster_max_hp' => $log->monster_max_hp,
                'monster_attack' => $log->monster_attack,
                'monster_defense' => $log->monster_defense,
                'monster_experience' => $log->monster_experience,
                'monster_copper' => $log->monster_copper,
                // 伤害详情
                'base_attack_damage' => $log->base_attack_damage,
                'skill_damage' => $log->skill_damage,
                'crit_damage' => $log->crit_damage,
                'aoe_damage' => $log->aoe_damage,
                'total_damage_to_monsters' => $log->total_damage_to_monsters,
                'monster_defense_reduction' => $log->monster_defense_reduction,
                'monster_counter_damage' => $log->monster_counter_damage,
                // 战斗详情
                'round_number' => $log->round_number,
                'monsters_alive_count' => $log->monsters_alive_count,
                'monsters_killed_count' => $log->monsters_killed_count,
                // 难度相关
                'difficulty_tier' => $log->difficulty_tier,
                'difficulty_multiplier' => $log->difficulty_multiplier,
            ];
        })->toArray();
    }
}
