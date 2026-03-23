<?php

namespace App\Http\Controllers\Api\Nav;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nav\ItemRequest;
use App\Models\Nav\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * 获取所有导航项
     */
    public function index(Request $request): JsonResponse
    {
        $query = Item::with('category');

        // 按分类筛选
        if ($request->has('category_id')) {
            $query->where('nav_category_id', $request->category_id);
        }

        // 默认只显示可见的
        if (! $request->has('show_all')) {
            $query->where('is_visible', true);
        }

        $items = $query->orderBy('sort_order')->get();

        return response()->json($items);
    }

    /**
     * 创建导航项
     */
    public function store(ItemRequest $request): JsonResponse
    {
        $item = Item::create($request->validated());

        return response()->json([
            'message' => '导航项创建成功',
            'item' => $item,
        ], 201);
    }

    /**
     * 显示指定导航项
     */
    public function show(Item $item): JsonResponse
    {
        $item->load('category');

        return response()->json($item);
    }

    /**
     * 更新指定导航项
     */
    public function update(ItemRequest $request, Item $item): JsonResponse
    {
        $item->update($request->validated());

        return response()->json([
            'message' => '导航项更新成功',
            'item' => $item,
        ]);
    }

    /**
     * 删除指定导航项
     */
    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'message' => '导航项删除成功',
        ]);
    }

    /**
     * 记录点击
     */
    public function recordClick(Item $item): JsonResponse
    {
        $item->incrementClicks();

        return response()->json([
            'message' => '点击记录成功',
            'clicks' => $item->clicks,
        ]);
    }
}
