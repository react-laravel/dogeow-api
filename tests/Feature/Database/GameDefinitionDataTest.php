<?php

namespace Tests\Feature\Database;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\Game\GameSkillDefinition;
use Database\Seeders\Game\GameFactorySeeder;
use Database\Seeders\Game\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameDefinitionDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_seeder_populates_canonical_rpg_definition_data(): void
    {
        $this->seed(GameSeeder::class);

        $expectedItems = count(require base_path('database/seeders/Game/Data/items.php'));
        $expectedMaps = count(require base_path('database/seeders/Game/Data/maps.php'));
        $expectedMonsters = count(require base_path('database/seeders/Game/Data/monsters.php'));

        $this->assertSame($expectedItems, GameItemDefinition::query()->count());
        $this->assertSame($expectedMaps, GameMapDefinition::query()->count());
        $this->assertSame($expectedMonsters, GameMonsterDefinition::query()->count());
        $this->assertGreaterThan(0, GameSkillDefinition::query()->count());
        $this->assertFalse(
            GameItemDefinition::query()
                ->where('type', 'weapon')
                ->get()
                ->contains(fn (GameItemDefinition $item): bool => array_key_exists('max_hp', $item->base_stats ?? [])),
            'Weapon definitions must not grant max_hp.'
        );

        $map = GameMapDefinition::query()
            ->where('name', '新手营地')
            ->where('act', 1)
            ->firstOrFail();

        $monsterIds = $map->monster_ids ?? [];

        $this->assertNotEmpty($monsterIds);
        $this->assertSame(
            count($monsterIds),
            GameMonsterDefinition::query()->whereIn('id', $monsterIds)->where('is_active', true)->count()
        );

        $newbieMonsters = GameMonsterDefinition::query()
            ->whereIn('id', $monsterIds)
            ->get();

        $this->assertCount(3, $newbieMonsters);
        $this->assertSame(
            ['猪', '鹿', '兔子'],
            $newbieMonsters->sortBy('id')->pluck('name')->values()->all()
        );
        $this->assertSame(0, (int) $newbieMonsters->firstWhere('name', '猪')?->attack_base);
        $this->assertSame(3, (int) $newbieMonsters->firstWhere('name', '猪')?->hp_base);
        $this->assertSame(1, (int) $newbieMonsters->firstWhere('name', '猪')?->defense_base);
        $this->assertSame(1, (int) $newbieMonsters->firstWhere('name', '猪')?->experience_base);
        $this->assertSame(0, (int) $newbieMonsters->firstWhere('name', '鹿')?->attack_base);
        $this->assertSame(2, (int) $newbieMonsters->firstWhere('name', '鹿')?->hp_base);
        $this->assertSame(2, (int) $newbieMonsters->firstWhere('name', '鹿')?->defense_base);
        $this->assertSame(1, (int) $newbieMonsters->firstWhere('name', '鹿')?->experience_base);
        $this->assertSame(0, (int) $newbieMonsters->firstWhere('name', '兔子')?->attack_base);
        $this->assertSame(1, (int) $newbieMonsters->firstWhere('name', '兔子')?->hp_base);
        $this->assertSame(3, (int) $newbieMonsters->firstWhere('name', '兔子')?->defense_base);
        $this->assertSame(1, (int) $newbieMonsters->firstWhere('name', '兔子')?->experience_base);

        foreach ($newbieMonsters as $monster) {
            $this->assertIsArray($monster->drop_table, "{$monster->name} should have an RPG drop table");
            $this->assertGreaterThan(0, $monster->drop_table['item_chance'] ?? 0, "{$monster->name} should be able to drop equipment");
            $this->assertGreaterThan(0, $monster->drop_table['potion_chance'] ?? 0, "{$monster->name} should be able to drop potions");
            $this->assertNotEmpty($monster->drop_table['item_types'] ?? [], "{$monster->name} should define equipment types");
        }

        $secondMap = GameMapDefinition::query()
            ->where('name', '幽暗森林')
            ->where('act', 1)
            ->firstOrFail();

        $secondLayerMonsters = GameMonsterDefinition::query()
            ->whereIn('id', $secondMap->monster_ids ?? [])
            ->get()
            ->keyBy('id');

        $this->assertSame(
            [
                ['hp' => 3, 'defense' => 6, 'attack' => 1, 'experience' => 4],
                ['hp' => 6, 'defense' => 4, 'attack' => 3, 'experience' => 4],
                ['hp' => 9, 'defense' => 2, 'attack' => 2, 'experience' => 4],
            ],
            collect($secondMap->monster_ids)
                ->map(fn (int $id): array => [
                    'hp' => (int) $secondLayerMonsters[$id]->hp_base,
                    'defense' => (int) $secondLayerMonsters[$id]->defense_base,
                    'attack' => (int) $secondLayerMonsters[$id]->attack_base,
                    'experience' => (int) $secondLayerMonsters[$id]->experience_base,
                ])
                ->all()
        );

        $allMapMonsterIds = GameMapDefinition::query()
            ->pluck('monster_ids')
            ->flatMap(fn (?array $ids): array => $ids ?? [])
            ->values();

        $this->assertSame($allMapMonsterIds->count(), $allMapMonsterIds->unique()->count());
    }

    public function test_game_definition_factories_create_valid_related_records(): void
    {
        $item = GameItemDefinition::factory()->potion()->create();
        $monster = GameMonsterDefinition::factory()->boss()->create();
        $map = GameMapDefinition::factory()->withMonsters(3)->create();

        $this->assertSame('potion', $item->type);
        $this->assertContains($item->sub_type, ['hp', 'mp']);
        $this->assertSame('boss', $monster->type);
        $this->assertCount(3, $map->monster_ids ?? []);
        $this->assertSame(
            3,
            GameMonsterDefinition::query()->whereIn('id', $map->monster_ids ?? [])->count()
        );
    }

    public function test_game_factory_seeder_populates_random_rpg_definitions(): void
    {
        $this->seed(GameFactorySeeder::class);

        $this->assertSame(30, GameItemDefinition::query()->count());
        $this->assertSame(12, GameSkillDefinition::query()->count());
        $this->assertSame(24, GameMonsterDefinition::query()->count());
        $this->assertSame(8, GameMapDefinition::query()->count());
        $this->assertTrue(
            GameMapDefinition::query()->get()->every(
                fn (GameMapDefinition $map): bool => is_array($map->monster_ids)
                    && count($map->monster_ids) === 3
            )
        );

        $allMapMonsterIds = GameMapDefinition::query()
            ->pluck('monster_ids')
            ->flatMap(fn (?array $ids): array => $ids ?? [])
            ->values();

        $this->assertSame($allMapMonsterIds->count(), $allMapMonsterIds->unique()->count());
    }
}
