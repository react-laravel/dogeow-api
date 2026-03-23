<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\TagRequest;
use App\Models\Thing\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    /**
     * 获取当前用户的所有物品标签
     */
    public function index(): JsonResponse
    {
        $tags = Tag::where('user_id', Auth::id())
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tags);
    }

    /**
     * 创建新的物品标签
     */
    public function store(TagRequest $request): JsonResponse
    {
        $tag = Tag::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'color' => $request->color ?? '#3b82f6',
        ]);

        return response()->json($tag, 201);
    }

    /**
     * 获取指定的物品标签
     */
    public function show(string $id): JsonResponse
    {
        $tag = Tag::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json($tag);
    }

    /**
     * 更新指定的物品标签
     */
    public function update(TagRequest $request, string $id): JsonResponse
    {
        $tag = Tag::where('user_id', Auth::id())
            ->findOrFail($id);

        $tag->update($request->validated());

        return response()->json($tag);
    }

    /**
     * 删除指定的物品标签
     */
    public function destroy(string $id): JsonResponse
    {
        $tag = Tag::where('user_id', Auth::id())
            ->findOrFail($id);

        // 先删除关联关系
        $tag->items()->detach();

        $tag->delete();

        return response()->json(null, 204);
    }
}
