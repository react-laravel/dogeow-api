<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\LocationRequest;
use App\Models\Thing\Area;
use App\Models\Thing\Room;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LocationAreaController extends Controller
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
     * 获取区域列表
     */
    public function index()
    {
        $areas = Area::where('user_id', Auth::id())
            ->withCount('rooms')
            ->get();

        return $this->success(['areas' => $areas], 'Areas retrieved successfully');
    }

    /**
     * 存储新创建的区域
     */
    public function store(LocationRequest $request)
    {
        $area = new Area($request->validated());
        $area->user_id = Auth::id();
        $area->save();

        return $this->success(['area' => $area], '区域创建成功', 201);
    }

    /**
     * 显示指定区域
     */
    public function show(Area $area)
    {
        if ($error = $this->authorizeOrFail('view', $area, '无权查看此区域')) {
            return $error;
        }

        return $this->success(['area' => $area->load('rooms')], 'Area retrieved successfully');
    }

    /**
     * 更新指定区域
     */
    public function update(LocationRequest $request, Area $area)
    {
        if ($error = $this->authorizeOrFail('update', $area, '无权更新此区域')) {
            return $error;
        }

        $area->update($request->validated());

        return $this->success(['area' => $area], '区域更新成功');
    }

    /**
     * 删除指定区域
     */
    public function destroy(Area $area)
    {
        if ($error = $this->authorizeOrFail('delete', $area, '无权删除此区域')) {
            return $error;
        }

        // 检查区域是否有关联的房间
        if ($area->rooms()->count() > 0) {
            return $this->error('无法删除已有房间的区域', [], 400);
        }

        $area->delete();

        return $this->success(null, '区域删除成功');
    }

    /**
     * 获取指定区域下的房间列表
     */
    public function rooms(Area $area)
    {
        if ($error = $this->authorizeOrFail('view', $area, '无权查看此区域的房间')) {
            return $error;
        }

        $rooms = Room::where('area_id', $area->id)
            ->where('user_id', Auth::id())
            ->with('area')
            ->withCount('spots')
            ->get();

        return $this->success(['rooms' => $rooms], 'Area rooms retrieved successfully');
    }

    /**
     * 设置默认区域
     */
    public function setDefault(Area $area)
    {
        if ($error = $this->authorizeOrFail('update', $area, '无权设置此区域为默认')) {
            return $error;
        }

        // 清除其他默认区域
        Area::where('user_id', Auth::id())->update(['is_default' => false]);

        // 设置新的默认区域
        $area->is_default = true;
        $area->save();

        return $this->success(['area' => $area], '默认区域设置成功');
    }
}
