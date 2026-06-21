<?php

namespace App\Http\Controllers\Api\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeGraphRequest;
use App\Models\KnowledgeGraph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KnowledgeGraphController extends Controller
{
    /**
     * 获取当前用户的所有图谱
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $graphs = KnowledgeGraph::forUser($user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'data', 'updated_at', 'created_at']);

        return response()->json($graphs);
    }

    /**
     * 创建新图谱
     */
    public function store(KnowledgeGraphRequest $request): JsonResponse
    {
        $user = Auth::user();
        $graph = KnowledgeGraph::create([
            'user_id' => $user->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'data' => $request->validated('data') ?? null,
        ]);

        return response()->json([
            'message' => '图谱创建成功',
            'graph' => $graph,
        ], 201);
    }

    /**
     * 获取指定图谱（含完整数据）
     */
    public function show(KnowledgeGraph $graph): JsonResponse
    {
        $this->authorizeOwner($graph);

        return response()->json($graph->only(['id', 'name', 'description', 'data', 'updated_at', 'created_at']));
    }

    /**
     * 更新图谱
     */
    public function update(KnowledgeGraphRequest $request, KnowledgeGraph $graph): JsonResponse
    {
        $this->authorizeOwner($graph);

        $graph->update($request->validated());

        return response()->json([
            'message' => '图谱更新成功',
            'graph' => $graph,
        ]);
    }

    /**
     * 删除图谱
     */
    public function destroy(KnowledgeGraph $graph): JsonResponse
    {
        $this->authorizeOwner($graph);
        $graph->delete();

        return response()->json([
            'message' => '图谱删除成功',
        ]);
    }

    /**
     * 验证当前用户是否是该图谱的拥有者
     */
    private function authorizeOwner(KnowledgeGraph $graph): void
    {
        $userId = Auth::id();
        abort_if($graph->user_id !== $userId, 403, '无权访问此图谱');
    }
}
