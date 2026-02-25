<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameMapDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    /**
     * 获取所有地图
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $maps = GameMapDefinition::query()
            ->where('is_active', true)
            ->orderBy('act')
            ->orderBy('id')
            ->get();

        $mapsWithMonsters = $maps->map(function (GameMapDefinition $map) {
            $arr = $map->toArray();
            $arr['monsters'] = array_values(array_map(
                fn ($m) => $m->toArray(),
                $map->getMonsters()
            ));

            return $arr;
        });

        return $this->success([
            'maps' => $mapsWithMonsters,
            'current_map_id' => $character->current_map_id,
        ]);
    }

    /**
     * 进入地图
     */
    public function enter(Request $request, int $mapId): JsonResponse
    {
        $character = $this->getCharacter($request);
        $map = GameMapDefinition::findOrFail($mapId);

        // 更新当前地图并自动开始战斗
        $character->current_map_id = $mapId;
        $character->is_fighting = true;
        $character->save();

        return $this->success([
            'character' => $character->fresh('currentMap'),
            'map' => $map,
        ], "已进入 {$map->name}");
    }

    /**
     * 传送到地图
     */
    public function teleport(Request $request, int $mapId): JsonResponse
    {
        $character = $this->getCharacter($request);
        $map = GameMapDefinition::findOrFail($mapId);

        // 直接传送到地图，自动开始战斗；若当前未在战斗中则视为复活，只恢复基础生命值与法力值
        $wasNotFighting = ! $character->is_fighting;
        $character->current_map_id = $mapId;
        $character->is_fighting = true;
        if ($wasNotFighting) {
            // 复活时恢复满血满蓝
            $character->current_hp = $character->getMaxHp();
            $character->current_mana = $character->getMaxMana();
        }
        $character->save();

        return $this->success([
            'character' => $character->fresh('currentMap'),
        ], "已传送到 {$map->name}");
    }

    /**
     * 获取当前地图信息
     */
    public function current(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        if (! $character->current_map_id) {
            return $this->success([
                'current_map' => null,
                'monsters' => [],
            ]);
        }

        $map = $character->currentMap;
        $monsters = $map ? $map->getMonsters() : [];

        return $this->success([
            'current_map' => $map,
            'monsters' => $monsters,
            'is_fighting' => $character->is_fighting,
        ]);
    }
}
