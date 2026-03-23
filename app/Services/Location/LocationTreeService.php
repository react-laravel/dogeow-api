<?php

namespace App\Services\Location;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class LocationTreeService extends BaseService
{
    /**
     * 构建位置树形结构
     */
    public function buildLocationTree(int $userId): array
    {
        // 获取当前用户的所有区域
        $areas = Area::where('user_id', $userId)
            ->withCount('rooms')
            ->orderBy('id')
            ->get();

        // 获取当前用户的所有房间
        $rooms = Room::where('user_id', $userId)
            ->withCount('spots')
            ->orderBy('id')
            ->get();

        // 获取当前用户的所有具体位置
        $spots = Spot::where('user_id', $userId)
            ->withCount('items')
            ->orderBy('id')
            ->get();

        // 获取物品数量统计(使用 Eloquent 聚合)
        $itemCounts = $this->getItemCounts($userId);

        // 构建树形结构
        $tree = $this->buildTreeStructure($areas, $rooms, $spots, $itemCounts);

        return [
            'tree' => $tree,
            'areas' => $areas,
            'rooms' => $rooms,
            'spots' => $spots,
        ];
    }

    /**
     * 获取物品数量统计
     */
    private function getItemCounts(int $userId): array
    {
        // 使用 Eloquent 关系查询替代 DB::table
        $areaItemCounts = DB::table('thing_items')
            ->where('user_id', $userId)
            ->whereNotNull('area_id')
            ->select('area_id', DB::raw('SUM(quantity) as items_count'))
            ->groupBy('area_id')
            ->pluck('items_count', 'area_id')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        $roomItemCounts = DB::table('thing_items')
            ->where('user_id', $userId)
            ->whereNotNull('room_id')
            ->select('room_id', DB::raw('SUM(quantity) as items_count'))
            ->groupBy('room_id')
            ->pluck('items_count', 'room_id')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        return [
            'areas' => $areaItemCounts,
            'rooms' => $roomItemCounts,
        ];
    }

    /**
     * 构建树形结构
     *
     * @param  Collection<int, Area>  $areas
     * @param  Collection<int, Room>  $rooms
     * @param  Collection<int, Spot>  $spots
     */
    private function buildTreeStructure(Collection $areas, Collection $rooms, Collection $spots, array $itemCounts): array
    {
        $tree = [];
        /** @var SupportCollection<int|string, Collection<int, Room>> $roomsByArea */
        $roomsByArea = $rooms->groupBy('area_id');
        /** @var SupportCollection<int|string, Collection<int, Spot>> $spotsByRoom */
        $spotsByRoom = $spots->groupBy('room_id');

        foreach ($areas as $area) {
            $areaNode = [
                'id' => 'area_' . $area->id,
                'name' => $area->name,
                'type' => 'area',
                'original_id' => $area->id,
                'children' => [],
                'items_count' => $itemCounts['areas'][$area->id] ?? 0,
            ];

            foreach ($roomsByArea->get($area->id, collect()) as $room) {
                $roomNode = [
                    'id' => 'room_' . $room->id,
                    'name' => $room->name,
                    'type' => 'room',
                    'original_id' => $room->id,
                    'parent_id' => $area->id,
                    'children' => [],
                    'items_count' => $itemCounts['rooms'][$room->id] ?? 0,
                ];

                foreach ($spotsByRoom->get($room->id, collect()) as $spot) {
                    $spotNode = [
                        'id' => 'spot_' . $spot->id,
                        'name' => $spot->name,
                        'type' => 'spot',
                        'original_id' => $spot->id,
                        'parent_id' => $room->id,
                        'items_count' => $spot->items_count,
                    ];

                    $roomNode['children'][] = $spotNode;
                }

                $areaNode['children'][] = $roomNode;
            }

            $tree[] = $areaNode;
        }

        return $tree;
    }
}
