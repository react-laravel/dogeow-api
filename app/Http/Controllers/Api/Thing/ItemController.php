<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\AddItemRelationRequest;
use App\Http\Requests\Thing\BatchAddItemRelationsRequest;
use App\Http\Requests\Thing\ItemRequest;
use App\Jobs\TriggerKnowledgeIndexBuildJob;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Services\Thing\ItemSearchService;
use App\Services\Thing\ItemService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ItemController extends Controller
{
    private const ITEM_RELATIONS = ['user', 'primaryImage', 'images', 'category', 'spot.room.area', 'tags'];

    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemSearchService $itemSearchService
    ) {}

    /**
     * 获取物品列表
     */
    public function index(Request $request): mixed
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
    private function getRequestLimit(Request $request): int
    {
        return (int) $request->input('limit', 10);
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
    public function store(ItemRequest $request): JsonResponse
    {
        $this->authorize('create', Item::class);

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
    public function show(Item $item): JsonResponse
    {
        try {
            $this->authorize('view', $item);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }

        return response()->json($item->load(self::ITEM_RELATIONS));
    }

    /**
     * 更新指定物品
     */
    public function update(ItemRequest $request, Item $item): JsonResponse
    {
        try {
            $this->authorize('update', $item);
        } catch (AuthorizationException $e) {
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
    public function destroy(Item $item): JsonResponse|Response
    {
        $this->authorize('delete', $item);

        return DB::transaction(function () use ($item) {
            $item->images()->delete();
            $item->delete();

            // return 204 No Content for successful deletion
            return response()->noContent();
        });
    }

    /**
     * 获取物品的关联列表
     */
    public function relations(Item $item): JsonResponse
    {
        try {
            $this->authorize('view', $item);
        } catch (AuthorizationException $e) {
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
    public function addRelation(AddItemRelationRequest $request, Item $item): JsonResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validated();
        $relatedItemId = (int) $validated['related_item_id'];

        if ($item->id === $relatedItemId) {
            return response()->json(['message' => '不能关联自己'], 400);
        }

        $relatedItem = Item::query()->findOrFail($relatedItemId);
        try {
            $this->authorize('view', $relatedItem);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => '无权访问关联的物品'], 403);
        }

        try {
            $item->addRelation(
                $relatedItemId,
                $validated['relation_type'],
                $validated['description'] ?? null
            );

            return response()->json([
                'message' => '关联添加成功',
                'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
            ], 201);
        } catch (\Throwable $e) {
            if ($this->isDuplicateRelationException($e)) {
                return response()->json(['message' => '该关联已存在'], 400);
            }

            Log::error('添加物品关联失败', [
                'item_id' => $item->id,
                'related_item_id' => $relatedItemId,
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json(['message' => '添加关联失败'], 500);
        }
    }

    /**
     * 删除物品关联
     */
    public function removeRelation(Item $item, int $relatedItemId): JsonResponse
    {
        $this->authorize('update', $item);

        $item->removeRelation($relatedItemId);

        return response()->json([
            'message' => '关联删除成功',
            'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
        ]);
    }

    /**
     * 批量添加关联
     */
    public function batchAddRelations(BatchAddItemRelationsRequest $request, Item $item): JsonResponse
    {
        try {
            $this->authorize('update', $item);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }

        $validated = $request->validated();

        $successCount = 0;
        $errors = [];

        foreach ($validated['relations'] as $relation) {
            $relatedItemId = (int) $relation['related_item_id'];

            if ($item->id === $relatedItemId) {
                $errors[] = [
                    'related_item_id' => $relatedItemId,
                    'error' => '不能关联自己',
                ];

                continue;
            }

            $relatedItem = Item::query()->find($relatedItemId);
            if (! $relatedItem) {
                $errors[] = [
                    'related_item_id' => $relatedItemId,
                    'error' => '无权访问关联的物品',
                ];

                continue;
            }

            try {
                $this->authorize('view', $relatedItem);
            } catch (AuthorizationException $e) {
                $errors[] = [
                    'related_item_id' => $relatedItemId,
                    'error' => '无权访问关联的物品',
                ];

                continue;
            }

            try {
                $item->addRelation(
                    $relatedItemId,
                    $relation['relation_type'],
                    $relation['description'] ?? null
                );
                $successCount++;
            } catch (\Throwable $e) {
                Log::warning('批量添加物品关联失败', [
                    'item_id' => $item->id,
                    'related_item_id' => $relatedItemId,
                    'user_id' => Auth::id(),
                    'exception' => $e,
                ]);

                $errors[] = [
                    'related_item_id' => $relatedItemId,
                    'error' => $this->isDuplicateRelationException($e) ? '该关联已存在' : '添加关联失败',
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
    public function categories(): JsonResponse
    {
        return response()->json(ItemCategory::where('user_id', Auth::id())->get());
    }

    /**
     * 应用可见性过滤条件
     */
    private function applyVisibilityFilter(mixed $query): void
    {
        if (Auth::check()) {
            $query->where(fn ($q) => $q->where('is_public', true)->orWhere('user_id', Auth::id()));

            return;
        }

        $query->where('is_public', true);
    }

    /**
     * 应用分类过滤器
     */
    private function applyCategoryFilter(mixed $query, mixed $value): mixed
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

    private function isDuplicateRelationException(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
