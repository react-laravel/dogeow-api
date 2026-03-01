<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\ItemRequest;
use App\Jobs\TriggerKnowledgeIndexBuildJob;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Services\Thing\ItemSearchService;
use App\Services\Thing\ItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ItemController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 10;

    private const ITEM_RELATIONS = ['user', 'images', 'category', 'spot.room.area', 'tags'];

    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemSearchService $itemSearchService
    ) {}

    /**
     * 获取物品列表
     */
    public function index(Request $request)
    {
        $baseQuery = Item::with(self::ITEM_RELATIONS);

        $this->applyVisibilityFilter($baseQuery);

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters($this->getAllowedFilters())
            ->defaultSort('-created_at');

        return $query->jsonPaginate();
    }

    /**
     * 获取请求限制
     */
    private function getRequestLimit(Request $request, int $default = self::DEFAULT_PAGE_SIZE): int
    {
        return (int) $request->get('limit', $default);
    }

    /**
     * 获取允许的过滤器
     */
    private function getAllowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn ($query, $value) => $query->where('name', 'like', "%{$value}%")),

            AllowedFilter::callback('description', fn ($query, $value) => $query->where('description', 'like', "%{$value}%")),

            AllowedFilter::callback('status', fn ($query, $value) => $value !== 'all' ? $query->where('status', $value) : $query),

            AllowedFilter::callback('tags', fn ($query, $value) => $query->whereHas('tags', fn ($q) => $q->whereIn('thing_tags.id', is_array($value) ? $value : explode(',', $value)))),

            AllowedFilter::callback('search', fn ($query, $value) => $query->search($value)),

            AllowedFilter::callback('purchase_date_from', fn ($query, $value) => $query->whereDate('purchase_date', '>=', $value)),

            AllowedFilter::callback('purchase_date_to', fn ($query, $value) => $query->whereDate('purchase_date', '<=', $value)),

            AllowedFilter::callback('expiry_date_from', fn ($query, $value) => $query->whereDate('expiry_date', '>=', $value)),

            AllowedFilter::callback('expiry_date_to', fn ($query, $value) => $query->whereDate('expiry_date', '<=', $value)),

            AllowedFilter::callback('price_from', fn ($query, $value) => $query->where('purchase_price', '>=', $value)),

            AllowedFilter::callback('price_to', fn ($query, $value) => $query->where('purchase_price', '<=', $value)),

            AllowedFilter::callback('area_id', fn ($query, $value) => $query->where(fn ($q) => $q->where('area_id', $value)
                ->orWhereHas('spot.room.area', fn ($subQ) => $subQ->where('thing_areas.id', $value)))),

            AllowedFilter::callback('room_id', fn ($query, $value) => $query->where(fn ($q) => $q->where('room_id', $value)
                ->orWhereHas('spot.room', fn ($subQ) => $subQ->where('thing_rooms.id', $value)))),

            AllowedFilter::callback('spot_id', fn ($query, $value) => $query->where('spot_id', $value)),

            AllowedFilter::callback('category_id', fn ($query, $value) => $this->applyCategoryFilter($query, $value)),

            AllowedFilter::callback('own', fn ($query, $value) => $value && Auth::check() ? $query->where('user_id', Auth::id()) : $query),
        ];
    }

    /**
     * 存储新创建的物品
     */
    public function store(ItemRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $validated['quantity'] ??= 1;

            $item = Item::create([
                ...$validated,
                'user_id' => Auth::id(),
            ]);

            $this->itemService->processItemImages($request, $item);
            $this->itemService->handleTags($request, $item);

            TriggerKnowledgeIndexBuildJob::dispatch();

            return response()->json([
                'message' => '物品创建成功',
                'item' => $item->load(self::ITEM_RELATIONS),
            ], 201);
        });
    }

    /**
     * 显示指定物品
     */
    public function show(Item $item)
    {
        if (! $this->canViewItem($item)) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }

        return response()->json($item->load(self::ITEM_RELATIONS));
    }

    /**
     * 更新指定物品
     */
    public function update(ItemRequest $request, Item $item)
    {
        if (! $this->canModifyItem($item)) {
            return response()->json(['message' => '无权更新此物品'], 403);
        }

        return DB::transaction(function () use ($request, $item) {
            $item->update($request->validated());

            $this->itemService->processItemImageUpdates($request, $item);
            $this->itemService->handleTags($request, $item);

            TriggerKnowledgeIndexBuildJob::dispatch();

            return response()->json([
                'message' => '物品更新成功',
                'item' => $item->load(self::ITEM_RELATIONS),
            ]);
        });
    }

    /**
     * 删除指定物品
     */
    public function destroy(Item $item)
    {
        if (! $this->canModifyItem($item)) {
            return response()->json(['message' => '无权删除此物品'], 403);
        }

        return DB::transaction(function () use ($item) {
            $item->images()->delete();
            $item->delete();

            // return 204 No Content for successful deletion
            return response()->noContent();
        });
    }

    /**
     * 增强搜索物品功能
     */
    public function search(Request $request)
    {
        $searchTerm = $request->get('q', '');
        $limit = $this->getRequestLimit($request);

        if (empty($searchTerm)) {
            return response()->json([
                'search_term' => $searchTerm,
                'count' => 0,
                'results' => [],
            ]);
        }

        $query = $this->itemSearchService->buildSearchQuery($searchTerm, self::ITEM_RELATIONS);

        $this->applyVisibilityFilter($query);

        $results = $query->limit($limit)->get();

        $this->itemSearchService->recordSearchHistory($searchTerm, $results->count(), $request);

        return response()->json([
            'search_term' => $searchTerm,
            'count' => $results->count(),
            'results' => $results,
        ]);
    }

    /**
     * 获取搜索建议
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->get('q', '');
        $limit = $this->getRequestLimit($request, 5);

        $suggestions = $this->itemSearchService->getSuggestions($query, $limit);

        return response()->json($suggestions);
    }

    /**
     * 获取搜索历史
     */
    public function searchHistory(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([]);
        }

        $limit = $this->getRequestLimit($request);
        $history = $this->itemSearchService->getUserHistory(Auth::id(), $limit);

        return response()->json($history);
    }

    /**
     * 清除搜索历史
     */
    public function clearSearchHistory(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['message' => '未登录'], 401);
        }

        $this->itemSearchService->clearUserHistory(Auth::id());

        return response()->json(['message' => '搜索历史已清除']);
    }

    /**
     * 获取物品的关联列表
     */
    public function relations(Item $item)
    {
        if (! $this->canViewItem($item)) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }

        $relations = [
            'related_items' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
            'relating_items' => $item->relatingItems()->with(self::ITEM_RELATIONS)->get(),
        ];

        return response()->json($relations);
    }

    /**
     * 添加物品关联
     */
    public function addRelation(Request $request, Item $item)
    {
        if (! $this->canModifyItem($item)) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }

        $request->validate([
            'related_item_id' => 'required|exists:thing_items,id',
            'relation_type' => 'required|in:accessory,replacement,related,bundle,parent,child',
            'description' => 'nullable|string|max:500',
        ]);

        $relatedItemId = $request->input('related_item_id');

        if ($item->id === $relatedItemId) {
            return response()->json(['message' => '不能关联自己'], 400);
        }

        $relatedItem = Item::find($relatedItemId);
        if (! $this->canViewItem($relatedItem)) {
            return response()->json(['message' => '无权访问关联的物品'], 403);
        }

        try {
            $item->addRelation(
                $relatedItemId,
                $request->input('relation_type', 'related'),
                $request->input('description')
            );

            return response()->json([
                'message' => '关联添加成功',
                'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
            ], 201);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return response()->json(['message' => '该关联已存在'], 400);
            }

            return response()->json(['message' => '添加关联失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 删除物品关联
     */
    public function removeRelation(Item $item, int $relatedItemId)
    {
        if (! $this->canModifyItem($item)) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }

        $item->removeRelation($relatedItemId);

        return response()->json([
            'message' => '关联删除成功',
            'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
        ]);
    }

    /**
     * 批量添加关联
     */
    public function batchAddRelations(Request $request, Item $item)
    {
        if (! $this->canModifyItem($item)) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }

        $request->validate([
            'relations' => 'required|array',
            'relations.*.related_item_id' => 'required|exists:thing_items,id',
            'relations.*.relation_type' => 'required|in:accessory,replacement,related,bundle,parent,child',
            'relations.*.description' => 'nullable|string|max:500',
        ]);

        $successCount = 0;
        $errors = [];

        foreach ($request->input('relations') as $relation) {
            try {
                $item->addRelation(
                    $relation['related_item_id'],
                    $relation['relation_type'],
                    $relation['description'] ?? null
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'related_item_id' => $relation['related_item_id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "成功添加 {$successCount} 个关联",
            'success_count' => $successCount,
            'errors' => $errors,
            'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
        ]);
    }

    /**
     * 获取用户的物品分类
     */
    public function categories()
    {
        return response()->json(ItemCategory::where('user_id', Auth::id())->get());
    }

    /**
     * 应用可见性过滤条件
     */
    private function applyVisibilityFilter($query): void
    {
        if (Auth::check()) {
            $query->where(fn ($q) => $q->where('is_public', true)->orWhere('user_id', Auth::id()));

            return;
        }

        $query->where('is_public', true);
    }

    /**
     * 检查用户是否有权限查看物品
     */
    private function canViewItem(Item $item): bool
    {
        return $item->is_public || (Auth::check() && $item->user_id === Auth::id());
    }

    /**
     * 检查用户是否有权限修改物品
     */
    private function canModifyItem(Item $item): bool
    {
        return Auth::check() && $item->user_id === Auth::id();
    }

    /**
     * 应用分类过滤器
     */
    private function applyCategoryFilter($query, $value)
    {
        if ($value === 'uncategorized' || $value === null) {
            return $query->whereNull('category_id');
        }

        $category = ItemCategory::find($value);
        if (! $category) {
            return $query->where('category_id', $value);
        }

        $categoryIds = [$value];
        if ($category->isParent()) {
            $categoryIds = array_merge($categoryIds, $category->children()->pluck('id')->toArray());
        }

        return $query->whereIn('category_id', $categoryIds);
    }
}
