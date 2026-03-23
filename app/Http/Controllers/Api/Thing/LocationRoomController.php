<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\LocationRequest;
use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationRoomController extends Controller
{
    /**
     * 授权检查辅助方法，返回 JsonResponse 或 null(授权成功时)
     */
    private function authorizeOrFail(string $ability, mixed $model, ?string $errorMessage = null): ?JsonResponse
    {
        try {
            $this->authorize($ability, $model);

            return null;
        } catch (AuthorizationException $e) {
            return $this->error($errorMessage ?? '无权执行此操作', [], 403);
        }
    }

    /**
     * 获取房间列表
     */
    public function index(Request $request)
    {
        $query = Room::where('user_id', Auth::id())
            ->with('area')
            ->withCount('spots');

        // 如果指定了区域 ID，则只获取该区域下的房间
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        $rooms = $query->get();

        return $this->success(['rooms' => $rooms], 'Rooms retrieved successfully');
    }

    /**
     * 存储新创建的房间
     */
    public function store(LocationRequest $request)
    {
        $area = Area::findOrFail($request->area_id);
        if ($error = $this->authorizeOrFail('createForArea', [Room::class, $area], '无权在此区域创建房间')) {
            return $error;
        }

        $room = new Room($request->validated());
        $room->user_id = Auth::id();
        $room->save();

        return $this->success(['room' => $room], '房间创建成功', 201);
    }

    /**
     * 显示指定房间
     */
    public function show(Room $room)
    {
        if ($error = $this->authorizeOrFail('view', $room, '无权查看此房间')) {
            return $error;
        }

        return $this->success(['room' => $room->load(['area', 'spots'])], 'Room retrieved successfully');
    }

    /**
     * 更新指定房间
     */
    public function update(LocationRequest $request, Room $room)
    {
        if ($error = $this->authorizeOrFail('update', $room, '无权更新此房间')) {
            return $error;
        }

        // 检查 area_id 是否属于当前用户
        if ($request->filled('area_id')) {
            $area = Area::find($request->input('area_id'));
            if (! $area || $area->user_id !== auth()->id()) {
                return $this->error('无权将房间移动到此区域', [], 403);
            }
        }

        $room->update($request->validated());

        return $this->success(['room' => $room], '房间更新成功');
    }

    /**
     * 删除指定房间
     */
    public function destroy(Room $room)
    {
        if ($error = $this->authorizeOrFail('delete', $room, '无权删除此房间')) {
            return $error;
        }

        // 检查房间是否有关联的位置
        if ($room->spots()->count() > 0) {
            return $this->error('无法删除已有具体位置的房间', [], 400);
        }

        $room->delete();

        return $this->success(null, '房间删除成功');
    }

    /**
     * 获取指定房间下的位置列表
     */
    public function spots(Room $room)
    {
        if ($error = $this->authorizeOrFail('view', $room, '无权查看此房间的位置')) {
            return $error;
        }

        $spots = Spot::where('room_id', $room->id)
            ->where('user_id', Auth::id())
            ->with('room.area')
            ->get();

        return $this->success(['spots' => $spots], 'Room spots retrieved successfully');
    }
}
