<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\CategoryRequest;
use App\Http\Resources\ApiResponse;
use App\Models\Thing\ItemCategory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    /**
     * 获取分类列表
     */
    public function index()
    {
        $categories = ItemCategory::where('user_id', Auth::id())
            ->with([
                'parent',
                'children' => fn ($query) => $query->withCount('items'),
            ])
            ->withCount('items')
            ->orderBy('parent_id', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        // 计算父分类的总物品数量（含子分类；须从扁平列表汇总，嵌套 children 无 withCount）
        $categories->each(function ($category) use ($categories) {
            if (! $category->isParent()) {
                return;
            }

            $childrenItems = $categories
                ->where('parent_id', $category->id)
                ->sum(fn (ItemCategory $child) => (int) $child->items_count);

            $category->setAttribute('items_count', (int) $category->items_count + $childrenItems);
        });

        return ApiResponse::success($categories);
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

            if (! $parentCategory || ! Gate::allows('view', $parentCategory)) {
                return ApiResponse::error('指定的父分类不存在或无权访问');
            }

            // 防止创建三级分类(子分类不能再有子分类)
            if ($parentCategory->parent_id !== null) {
                return ApiResponse::error('不能在子分类下创建分类');
            }
        }

        $category = new ItemCategory($validated);
        $category->user_id = Auth::id();
        $category->save();

        return ApiResponse::created($category->load(['parent', 'children']), '分类创建成功');
    }

    /**
     * 显示指定分类
     */
    public function show(ItemCategory $category)
    {
        try {
            $this->authorize('view', $category);
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden('无权查看此分类');
        }

        return ApiResponse::success($category->load('items'));
    }

    /**
     * 更新指定分类
     */
    public function update(CategoryRequest $request, ItemCategory $category)
    {
        try {
            $this->authorize('update', $category);
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden('无权更新此分类');
        }

        $category->update($request->validated());

        return ApiResponse::updated($category, '分类更新成功');
    }

    /**
     * 删除指定分类
     */
    public function destroy(ItemCategory $category)
    {
        try {
            $this->authorize('delete', $category);
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden('无权删除此分类');
        }

        // 检查分类是否有关联的物品
        if ($category->items()->count() > 0) {
            return ApiResponse::error('无法删除已有物品的分类');
        }

        // 检查是否有子分类
        if ($category->children()->count() > 0) {
            return ApiResponse::error('无法删除有子分类的分类');
        }

        $category->delete();

        return ApiResponse::deleted('分类删除成功');
    }
}
