<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Services\Thing\ItemSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ItemSearchController extends Controller
{
    private const ITEM_RELATIONS = ['category', 'tags', 'spot', 'spot.room', 'spot.room.area'];

    private const SEARCH_HISTORY_LIMIT = 10;

    public function __construct(
        private readonly ItemSearchService $itemSearchService
    ) {}

    /**
     * Search items
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $request->input('q', '');
        $limit = (int) $request->input('limit', 20);
        $userId = $request->user()?->id;

        if (empty($query)) {
            return response()->json([
                'search_term' => $query,
                'count' => 0,
                'results' => [],
            ]);
        }

        $searchQuery = $this->itemSearchService->buildSearchQuery($query, self::ITEM_RELATIONS);

        // Apply visibility filter
        $searchQuery->where('is_public', true);

        $results = $searchQuery->limit($limit)->get();

        // Record search history if user is authenticated
        if ($userId) {
            $this->itemSearchService->recordSearchHistoryWithUser($userId, $query, $results->count(), $request);
        }

        return response()->json([
            'search_term' => $query,
            'count' => $results->count(),
            'results' => $results,
        ]);
    }

    /**
     * Search suggestions (autocomplete)
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $query = $request->input('q');
        $limit = (int) $request->input('limit', 5);

        $suggestions = $this->itemSearchService->getSuggestions($query, $limit);

        return response()->json($suggestions);
    }

    /**
     * Get search history
     */
    public function searchHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $limit = (int) $request->input('limit', self::SEARCH_HISTORY_LIMIT);

        $history = $this->itemSearchService->getUserHistory($userId, $limit);

        return response()->json(['history' => $history]);
    }

    /**
     * Clear search history
     */
    public function clearSearchHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $this->itemSearchService->clearUserHistory($userId);

        Log::info('Search history cleared', ['user_id' => $userId]);

        return response()->json(['message' => '搜索历史已清除']);
    }
}
