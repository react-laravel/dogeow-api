<?php

namespace App\Services\Thing;

use App\Models\Thing\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemSearchService
{
    /**
     * 构建增强搜索查询
     */
    public function buildSearchQuery(string $searchTerm, array $relations = [])
    {
        return Item::with($relations)
            ->where(function ($q) use ($searchTerm) {
                // 搜索名称和描述
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                  // 搜索分类名称
                    ->orWhereHas('category', function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'like', "%{$searchTerm}%");
                    })
                  // 搜索标签名称
                    ->orWhereHas('tags', function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'like', "%{$searchTerm}%");
                    })
                  // 搜索位置信息
                    ->orWhereHas('spot', function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('spot.room', function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('spot.room.area', function ($subQ) use ($searchTerm) {
                        $subQ->where('name', 'like', "%{$searchTerm}%");
                    });
            });
    }

    /**
     * 记录搜索历史
     */
    public function recordSearchHistory(string $searchTerm, int $resultsCount, Request $request): void
    {
        try {
            DB::table('thing_search_history')->insert([
                'user_id' => Auth::id(),
                'search_term' => $searchTerm,
                'results_count' => $resultsCount,
                'filters' => json_encode($request->except(['q', 'limit'])),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // 记录失败不影响主要功能
            Log::warning('记录搜索历史失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取搜索建议
     */
    public function getSuggestions(string $query, int $limit = 5)
    {
        if (empty($query)) {
            return collect();
        }

        return DB::table('thing_search_history')
            ->where('search_term', 'like', "%{$query}%")
            ->when(Auth::check(), function ($q) {
                return $q->where('user_id', Auth::id());
            })
            ->groupBy('search_term')
            ->orderByRaw('COUNT(*) desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->pluck('search_term');
    }

    /**
     * 获取用户搜索历史
     */
    public function getUserHistory(?int $userId, int $limit = 10)
    {
        if (! $userId) {
            return collect();
        }

        return DB::table('thing_search_history')
            ->select('search_term', DB::raw('MAX(created_at) as last_searched'), DB::raw('COUNT(*) as search_count'))
            ->where('user_id', $userId)
            ->groupBy('search_term')
            ->orderBy('last_searched', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 清除搜索历史
     */
    public function clearUserHistory(int $userId): void
    {
        DB::table('thing_search_history')
            ->where('user_id', $userId)
            ->delete();
    }
}
