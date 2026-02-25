<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\CategoryRequest;
use App\Models\Thing\ItemCategory;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * 获取分类列表
     */
    public function index()
    {
        $categories = ItemCategory::where('user_id', Auth::id())
            ->with(['parent', 'children.items'])
            ->withCount('items')
            ->orderBy('parent_id', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        // 计算父分类的总物品数量（包括子分类的物品）
        $categories->each(function ($category) {
            if ($category->isParent()) {
                // 计算所有子分类的物品数量总和
                $totalItems = $category->items_count;
                $childrenItems = $category->children->sum('items_count');
                $category->setAttribute('items_count', $totalItems + $childrenItems);
            }
        });

        return response()->json($categories);
    }

    /**
     * 存储新创建的分类
     */
    public function store(CategoryRequest $request)
    {
        $validated = $request->validated();

        // 如果指定了父分类，验证父分类是否属于当前用户
        if (isset($validated['parent_id'])) {
            $parentCategory = ItemCategory::find($validated['parent_id']);
            if (! $parentCategory || $parentCategory->user_id !== Auth::id()) {
                return response()->json(['message' => '指定的父分类不存在或无权访问'], 400);
            }

            // 防止创建三级分类（子分类不能再有子分类）
            if ($parentCategory->parent_id !== null) {
                return response()->json(['message' => '不能在子分类下创建分类'], 400);
            }
        }

        $category = new ItemCategory($validated);
        $category->user_id = Auth::id();
        $category->save();

        return response()->json([
            'message' => '分类创建成功',
            'category' => $category->load(['parent', 'children']),
        ], 201);
    }

    /**
     * 显示指定分类
     */
    public function show(ItemCategory $category)
    {
        // 检查权限：只有分类所有者可以查看
        if ($category->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此分类'], 403);
        }

        return response()->json($category->load('items'));
    }

    /**
     * 更新指定分类
     */
    public function update(CategoryRequest $request, ItemCategory $category)
    {
        // 检查权限：只有分类所有者可以更新
        if ($category->user_id !== Auth::id()) {
            return response()->json(['message' => '无权更新此分类'], 403);
        }

        $category->update($request->validated());

        return response()->json([
            'message' => '分类更新成功',
            'category' => $category,
        ]);
    }

    /**
     * 删除指定分类
     */
    public function destroy(ItemCategory $category)
    {
        // 检查权限：只有分类所有者可以删除
        if ($category->user_id !== Auth::id()) {
            return response()->json(['message' => '无权删除此分类'], 403);
        }

        // 检查分类是否有关联的物品
        if ($category->items()->count() > 0) {
            return response()->json(['message' => '无法删除已有物品的分类'], 400);
        }

        // 检查是否有子分类
        if ($category->children()->count() > 0) {
            return response()->json(['message' => '无法删除有子分类的分类'], 400);
        }

        $category->delete();

        return response()->json(['message' => '分类删除成功']);
    }
}
