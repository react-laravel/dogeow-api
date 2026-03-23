<?php

namespace App\Http\Controllers\Api\Nav;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nav\CategoryRequest;
use App\Models\Nav\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * 获取所有导航分类
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        // 如果请求显示所有分类(用于管理界面选择分类)
        if ($request->has('show_all')) {
            $categories = $query->withCount('items')
                ->orderBy('sort_order')
                ->get();

            return response()->json($categories);
        }

        // 默认行为：只返回可见的分类，并加载其导航项
        $categories = $query->with(['items' => function ($itemQuery) use ($request) {
            if ($request->has('filter') && isset($request->input('filter')['name'])) {
                $name = $request->input('filter')['name'];
                $itemQuery->where('name', 'like', '%' . $name . '%');
            }
        }])
            ->orderBy('sort_order')
            ->where('is_visible', true)
            ->get();

        // 只返回有 items 的分类
        if ($request->has('filter') && isset($request->input('filter')['name'])) {
            $categories = $categories->filter(function ($category) {
                return $category->items->isNotEmpty();
            })->values();
        }

        return response()->json($categories);
    }

    /**
     * 获取所有导航分类(管理员)
     */
    public function all(): JsonResponse
    {
        $categories = Category::withCount('items')
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    /**
     * 创建导航分类
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json([
            'message' => '分类创建成功',
            'category' => $category,
        ], 201);
    }

    /**
     * 显示指定导航分类
     */
    public function show(Category $category): JsonResponse
    {
        $category->load('items');

        return response()->json($category);
    }

    /**
     * 更新指定导航分类
     */
    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'message' => '分类更新成功',
            'category' => $category,
        ]);
    }

    /**
     * 删除指定导航分类
     */
    public function destroy(Category $category): JsonResponse
    {
        // 检查分类下是否有导航项
        if ($category->items()->count() > 0) {
            return response()->json([
                'message' => '该分类下存在导航项，无法删除',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => '分类删除成功',
        ]);
    }
}
