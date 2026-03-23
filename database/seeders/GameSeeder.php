<?php

namespace Database\Seeders;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedItemDefinitions();
        $this->seedSkillDefinitions();
        $this->seedMonsterDefinitions();
        $this->seedMapDefinitions();
    }

    private function seedItemDefinitions(): void
    {
        DB::table('game_item_definitions')->truncate();

        $items = require __DIR__ . '/GameSeederData/items.php';

        foreach ($items as $item) {
            $assetKey = $item['asset_key'] ?? ('item_' . $item['id']);
            unset($item['asset_key']);

            GameItemDefinition::create(array_merge($item, [
                'icon' => $assetKey . '.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedSkillDefinitions(): void
    {
        $skillsDir = __DIR__ . '/GameSeederData/skills';
        $skillFiles = [
            'skills_warrior.php',
            'skills_mage.php',
            'skills_ranger.php',
            'skills_common.php',
        ];
        $skills = [];
        foreach ($skillFiles as $file) {
            $path = $skillsDir . '/' . $file;
            if (file_exists($path)) {
                $skills = array_merge($skills, require $path);
            }
        }

        // 技能派系映射
        $branchMap = [
            // 战士
            '重击' => ['branch' => 'warrior', 'tier' => 1],
            '战吼' => ['branch' => 'warrior', 'tier' => 1],
            '铁壁' => ['branch' => 'passive', 'tier' => 1],
            '冲锋' => ['branch' => 'warrior', 'tier' => 2],
            '旋风斩' => ['branch' => 'warrior', 'tier' => 2],
            '狂暴' => ['branch' => 'warrior', 'tier' => 2],
            '钢铁之躯' => ['branch' => 'passive', 'tier' => 2],
            '斩杀' => ['branch' => 'warrior', 'tier' => 3],
            // 法师 - 火系
            '火球术' => ['branch' => 'fire', 'tier' => 1],
            '烈焰风暴' => ['branch' => 'fire', 'tier' => 3],
            // 法师 - 冰系
            '冰霜新星' => ['branch' => 'ice', 'tier' => 1],
            '冰箭' => ['branch' => 'ice', 'tier' => 1],
            '冰河世纪' => ['branch' => 'ice', 'tier' => 3],
            // 法师 - 雷系
            '雷击' => ['branch' => 'lightning', 'tier' => 1],
            '连锁闪电' => ['branch' => 'lightning', 'tier' => 2],
            '雷霆万钧' => ['branch' => 'lightning', 'tier' => 3],
            // 法师 - 奥系
            '魔力涌动' => ['branch' => 'passive', 'tier' => 1],
            '魔法护盾' => ['branch' => 'arcane', 'tier' => 2],
            '奥术智慧' => ['branch' => 'passive', 'tier' => 2],
            '陨石术' => ['branch' => 'fire', 'tier' => 3],
            // 游侠
            '穿刺射击' => ['branch' => 'ranger', 'tier' => 1],
            '多重射击' => ['branch' => 'ranger', 'tier' => 2],
            '疾风步' => ['branch' => 'ranger', 'tier' => 3],
            '鹰眼' => ['branch' => 'passive', 'tier' => 1],
            '毒箭' => ['branch' => 'poison', 'tier' => 2],
            '闪避' => ['branch' => 'ranger', 'tier' => 2],
            '致命瞄准' => ['branch' => 'passive', 'tier' => 2],
            '箭雨' => ['branch' => 'ranger', 'tier' => 3],
            '暗影步' => ['branch' => 'ranger', 'tier' => 3],
            // 通用
            '治疗术' => ['branch' => 'healing', 'tier' => 1],
            '力量强化' => ['branch' => 'passive', 'tier' => 1],
            '敏捷强化' => ['branch' => 'passive', 'tier' => 1],
            '体力强化' => ['branch' => 'passive', 'tier' => 1],
            '能量强化' => ['branch' => 'passive', 'tier' => 1],
            '吸血' => ['branch' => 'passive', 'tier' => 2],
            '回蓝' => ['branch' => 'passive', 'tier' => 2],
            'HP 强化' => ['branch' => 'passive', 'tier' => 1],
            'MP 强化' => ['branch' => 'passive', 'tier' => 1],
        ];

        // 使用 updateOrCreate 根据名称更新或创建技能
        foreach ($skills as $skill) {
            $isActive = $skill['type'] === 'active';
            $baseDamage = $isActive ? ($skill['mana_cost'] * 2) : 0;
            $damagePerLevel = $isActive ? 5 : 0;
            $manaCostPerLevel = $isActive ? 2 : 0;

            $branchData = $branchMap[$skill['name']] ?? ['branch' => null, 'tier' => 1];

            \App\Models\Game\GameSkillDefinition::updateOrCreate(
                ['name' => $skill['name']],
                array_merge($skill, [
                    'type' => $skill['type'],
                    'class_restriction' => $skill['class_restriction'],
                    'mana_cost' => $skill['mana_cost'],
                    'cooldown' => $skill['cooldown'],
                    'description' => $skill['description'],
                    'effects' => $skill['effects'] ?? null,
                    'target_type' => $skill['target_type'] ?? 'single',
                    'icon' => ! empty($skill['effect_key'])
                        ? $skill['effect_key'] . '.png'
                        : 'skill_' . strtolower(str_replace(' ', '_', $skill['name'])) . '.png',
                    'is_active' => true,
                    'max_level' => 10,
                    'base_damage' => $baseDamage,
                    'damage_per_level' => $damagePerLevel,
                    'mana_cost_per_level' => $manaCostPerLevel,
                    'branch' => $branchData['branch'],
                    'tier' => $branchData['tier'],
                ])
            );
        }
    }

    private function seedMonsterDefinitions(): void
    {
        $monsters = require __DIR__ . '/GameSeederData/monsters.php';

        foreach ($monsters as $monster) {
            $assetKey = $monster['asset_key'] ?? ('monster_' . strtolower(str_replace(' ', '_', $monster['name'])));
            unset($monster['asset_key']);

            GameMonsterDefinition::create(array_merge($monster, [
                'icon' => $assetKey . '.png',
                'is_active' => true,
            ]));
        }
    }

    private function seedMapDefinitions(): void
    {
        $maps = require __DIR__ . '/GameSeederData/maps.php';

        // 按 ID 顺序取当前库中怪物，用于把配置里的“序号”转成真实 ID(避免多次 seed 后 ID 错位)
        $monsterIdsByOrder = GameMonsterDefinition::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();

        foreach ($maps as $index => $map) {
            $assetKey = $map['asset_key'] ?? ('map_' . ($index + 1));
            unset($map['asset_key']);
            $rawIds = $map['monster_ids'] ?? [];
            $resolvedIds = array_values(array_filter(array_map(
                fn ($ord) => $monsterIdsByOrder[$ord - 1] ?? null,
                array_map('intval', (array) $rawIds)
            )));
            if (empty($resolvedIds)) {
                $resolvedIds = array_slice($monsterIdsByOrder, 0, 2);
            }

            GameMapDefinition::updateOrCreate(
                [
                    'name' => $map['name'],
                    'act' => $map['act'],
                ],
                array_merge($map, [
                    'monster_ids' => $resolvedIds,
                    'background' => $assetKey . '.jpg',
                    'is_active' => true,
                ])
            );
        }

        // 为所有缺少怪物的地图补全 monster_ids(含历史/重复行)
        $mapList = $maps;
        GameMapDefinition::query()->chunk(50, function ($definitions) use ($mapList, $monsterIdsByOrder) {
            foreach ($definitions as $def) {
                $ids = $def->monster_ids;
                if (is_array($ids) && ! empty(array_filter($ids))) {
                    continue;
                }
                $match = collect($mapList)->first(
                    fn ($m) => $m['name'] === $def->name && (int) $m['act'] === (int) $def->act
                );
                if ($match && ! empty($monsterIdsByOrder)) {
                    $rawIds = $match['monster_ids'] ?? [];
                    $resolvedIds = array_values(array_filter(array_map(
                        fn ($ord) => $monsterIdsByOrder[$ord - 1] ?? null,
                        array_map('intval', (array) $rawIds)
                    )));
                    if (empty($resolvedIds)) {
                        $resolvedIds = array_slice($monsterIdsByOrder, 0, 2);
                    }
                    $def->update(['monster_ids' => $resolvedIds]);
                } elseif (! empty($monsterIdsByOrder)) {
                    $def->update(['monster_ids' => array_slice($monsterIdsByOrder, 0, 2)]);
                }
            }
        });
    }
}
