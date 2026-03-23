<?php

namespace App\Services\Location;

use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Services\BaseService;

class LocationService extends BaseService
{
    /**
     * 构建位置树形结构
     */
    public function buildLocationTree(int $userId): array
    {
        // 获取当前用户的所有区域
        $areas = Area::where('user_id', $userId)
            ->withCount('rooms')
            ->get();

        // 获取当前用户的所有房间
        $rooms = Room::where('user_id', $userId)
            ->with('area')
            ->withCount('spots')
            ->get();

        // 获取当前用户的所有具体位置
        $spots = Spot::where('user_id', $userId)
            ->with('room')
            ->withCount('items')
            ->get();

        // 使用 Eloquent 聚合获取物品数量
        $areaItemCounts = $this->getAreaItemCounts($userId);
        $roomItemCounts = $this->getRoomItemCounts($userId);

        // 构建树形结构
        $tree = [];

        foreach ($areas as $area) {
            $areaNode = [
                'id' => 'area_' . $area->id,
                'name' => $area->name,
                'type' => 'area',
                'original_id' => $area->id,
                'children' => [],
                'items_count' => $areaItemCounts[$area->id] ?? 0,
            ];

            // 添加该区域下的房间
            $areaRooms = $rooms->where('area_id', $area->id);
            foreach ($areaRooms as $room) {
                $roomNode = [
                    'id' => 'room_' . $room->id,
                    'name' => $room->name,
                    'type' => 'room',
                    'original_id' => $room->id,
                    'parent_id' => $area->id,
                    'children' => [],
                    'items_count' => $roomItemCounts[$room->id] ?? 0,
                ];

                // 添加该房间下的具体位置
                $roomSpots = $spots->where('room_id', $room->id);
                foreach ($roomSpots as $spot) {
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

        return [
            'tree' => $tree,
            'areas' => $areas,
            'rooms' => $rooms,
            'spots' => $spots,
        ];
    }

    /**
     * 获取每个区域的物品数量(使用 Eloquent 聚合)
     *
     * @return array<int, int>
     */
    private function getAreaItemCounts(int $userId): array
    {
        return Item::where('user_id', $userId)
            ->whereNotNull('area_id')
            ->selectRaw('area_id, SUM(quantity) as items_count')
            ->groupBy('area_id')
            ->pluck('items_count', 'area_id')
            ->toArray();
    }

    /**
     * 获取每个房间的物品数量(使用 Eloquent 聚合)
     *
     * @return array<int, int>
     */
    private function getRoomItemCounts(int $userId): array
    {
        return Item::where('user_id', $userId)
            ->whereNotNull('room_id')
            ->selectRaw('room_id, SUM(quantity) as items_count')
            ->groupBy('room_id')
            ->pluck('items_count', 'room_id')
            ->toArray();
    }
}
