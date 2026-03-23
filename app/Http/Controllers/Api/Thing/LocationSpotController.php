<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\LocationRequest;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationSpotController extends Controller
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
     * 获取具体位置列表
     */
    public function index(Request $request)
    {
        $query = Spot::where('user_id', Auth::id())
            ->with('room.area')
            ->withCount('items');

        // 如果指定了房间 ID，则只获取该房间下的具体位置
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        $spots = $query->get();

        return $this->success(['spots' => $spots], 'Spots retrieved successfully');
    }

    /**
     * 存储新创建的具体位置
     */
    public function store(LocationRequest $request)
    {
        $room = Room::findOrFail($request->room_id);
        if ($error = $this->authorizeOrFail('createForRoom', [Spot::class, $room], '无权在此房间创建具体位置')) {
            return $error;
        }

        $spot = new Spot($request->validated());
        $spot->user_id = Auth::id();
        $spot->save();

        return $this->success(['spot' => $spot], '具体位置创建成功', 201);
    }

    /**
     * 显示指定具体位置
     */
    public function show(Spot $spot)
    {
        if ($error = $this->authorizeOrFail('view', $spot, '无权查看此具体位置')) {
            return $error;
        }

        return $this->success(['spot' => $spot->load(['room.area', 'items'])], 'Spot retrieved successfully');
    }

    /**
     * 更新指定具体位置
     */
    public function update(LocationRequest $request, Spot $spot)
    {
        if ($error = $this->authorizeOrFail('update', $spot, '无权更新此具体位置')) {
            return $error;
        }

        // 检查 room_id 是否属于当前用户
        if ($request->filled('room_id')) {
            $room = Room::find($request->input('room_id'));
            if (! $room || $room->user_id !== auth()->id()) {
                return $this->error('无权将具体位置移动到此房间', [], 403);
            }
        }

        $spot->update($request->validated());

        return $this->success(['spot' => $spot], '具体位置更新成功');
    }

    /**
     * 删除指定具体位置
     */
    public function destroy(Spot $spot)
    {
        if ($error = $this->authorizeOrFail('delete', $spot, '无权删除此具体位置')) {
            return $error;
        }

        // 检查位置是否有关联的物品
        if ($spot->items()->count() > 0) {
            return $this->error('无法删除已有物品的具体位置', [], 400);
        }

        $spot->delete();

        return $this->success(null, '具体位置删除成功');
    }
}
