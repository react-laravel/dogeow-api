<?php

namespace App\Http\Controllers\Api\Note;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Jobs\TriggerKnowledgeIndexBuildJob;
use App\Models\Note\Note;
use App\Models\Note\NoteLink;
use App\Models\User;
use App\Services\Note\NoteContentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NoteController extends Controller
{
    public function __construct(
        private readonly NoteContentService $noteContentService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $view = $request->query('view', 'list'); // 'list' or 'graph'

        if ($view === 'graph') {
            return $this->getGraph();
        }

        $notes = Note::with(['category', 'tags'])
            ->where('user_id', $this->getCurrentUserId())
            ->select([
                'id',
                'user_id',
                'title',
                'note_category_id',
                'is_draft',
                'is_wiki',
                'slug',
                'summary',
                'created_at',
                'updated_at',
            ])
            // 列表仅返回正文预览，完整内容走 show
            ->selectRaw("LEFT(COALESCE(content_markdown, ''), 240) as content_markdown")
            ->orderBy('updated_at', 'desc')
            ->get();

        // for list views we return the raw array directly; frontend normalizers
        // can handle either wrapped or raw responses, and many tests expect
        // a plain list. Previously this used the success() helper which added
        // a message and "notes" key. Returning the raw collection keeps the
        // API simple and aligns with store/update behaviors.
        return $this->success($notes, 'Notes retrieved successfully');
    }

    /**
     * 获取完整图谱数据(公开)
     */
    public function getGraph(): JsonResponse
    {
        $nodes = $this->buildGraphNodes();
        $links = $this->buildGraphLinks();

        return $this->success([
            'nodes' => $nodes,
            'links' => $links,
        ], 'Graph retrieved successfully');
    }

    /**
     * 通过 slug 获取文章(公开)
     * 仅返回 wiki 文章，防止私有笔记通过 slug 泄露
     */
    public function getArticleBySlug(string $slug): JsonResponse
    {
        $note = Note::where('slug', $slug)
            ->where('is_wiki', true)
            ->first();

        if (! $note) {
            return $this->error('Article not found', [], 404);
        }

        return $this->success([
            'title' => $note->title,
            'slug' => $note->slug,
            'content' => $note->content,
            'content_markdown' => $note->content_markdown,
            'html' => $note->content,
        ], 'Article retrieved successfully');
    }

    /**
     * 批量获取所有 wiki 文章内容(公开)
     * 用于知识库批量加载，提高性能
     */
    public function getAllWikiArticles(): JsonResponse
    {
        try {
            $articles = Cache::remember('wiki:articles:all', 300, function () {
                return Note::where('is_wiki', true)
                    ->orderByDesc('updated_at')
                    ->get()
                    ->map(function (Note $note) {
                        return $this->mapWikiArticle($note);
                    });
            });

            return $this->success([
                'articles' => $articles,
            ], 'All wiki articles retrieved successfully');
        } catch (\Exception $e) {
            Log::error('getAllWikiArticles error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to retrieve articles', [], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NoteRequest $request): JsonResponse
    {
        $data = $this->prepareNoteData($request->validated());
        $data['user_id'] = $this->getCurrentUserId();

        $note = Note::create($data);

        // 处理标签
        if ($request->has('tags')) {
            $this->noteContentService->handleTags($note, $request->input('tags'));
        }

        $note->load('tags');

        Cache::forget('wiki:articles:all');
        TriggerKnowledgeIndexBuildJob::dispatch();

        return $this->success($note, 'Note created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $note = $this->findUserNote($id);
        $note->load(['category', 'tags']);

        // Return the note directly rather than wrapping it in the generic
        // success envelope. This keeps the payload predictable for clients
        // and matches the expectations of existing unit tests.
        return $this->success($note, 'Note retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNoteRequest $request, string $id): JsonResponse
    {
        $note = $this->findUserNote($id);

        // wiki 节点对所有认证用户可读，但写操作仍需所有者或管理员权限
        $this->authorizeNoteWrite($note);

        $validatedData = $this->prepareUpdateData($request->validated(), $request, $note);

        $note->update($validatedData);

        // 处理标签
        if ($request->has('tags')) {
            $this->noteContentService->handleTags($note, $request->input('tags'));
        }

        $note->load('tags');

        Cache::forget('wiki:articles:all');
        TriggerKnowledgeIndexBuildJob::dispatch();

        return $this->success($note, 'Note updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $note = $this->findUserNote($id);

        // wiki 节点对所有认证用户可读，但删除仍需所有者或管理员权限
        $this->authorizeNoteWrite($note);

        $note->delete();

        Cache::forget('wiki:articles:all');
        TriggerKnowledgeIndexBuildJob::dispatch();

        // return no content for successful deletion
        return response()->json([], 204);
    }

    /**
     * 查找用户的笔记或 wiki 节点
     */
    private function findUserNote(string $id): Note
    {
        // 先查找笔记(不限制条件)
        $note = Note::find($id);

        if (! $note) {
            throw new ModelNotFoundException(
                'No query results for model [App\\Models\\Note\\Note] ' . $id
            );
        }

        $userId = $this->getCurrentUserId();

        // 检查权限：
        // 1. 如果是用户自己的笔记(user_id 匹配)
        // 2. 或者是 wiki 节点(is_wiki = true，允许所有认证用户编辑)
        $isUserNote = $note->user_id === $userId;
        $isWikiNode = $note->is_wiki === true;

        if (! $isUserNote && ! $isWikiNode) {
            throw new ModelNotFoundException(
                'No query results for model [App\\Models\\Note\\Note] ' . $id
            );
        }

        return $note;
    }

    /**
     * 校验当前用户对笔记的写权限（更新/删除）。
     * 仅所有者或管理员可写，wiki 的可读性不等于可写性。
     */
    private function authorizeNoteWrite(Note $note): void
    {
        $isOwner = $note->user_id === $this->getCurrentUserId();

        if (! $isOwner && ! $this->currentUserIsAdmin()) {
            throw new AccessDeniedHttpException('You do not have permission to modify this note');
        }
    }

    private function currentUserIsAdmin(): bool
    {
        /** @var User|null $user */
        $user = request()->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * 构建图谱节点数据
     */
    private function buildGraphNodes()
    {
        // 获取所有 wiki 节点(is_wiki = true)和用户自己的笔记
        return Note::with('tags')
            ->where(function ($query) {
                $query->where('is_wiki', true)
                    ->orWhere('user_id', $this->getCurrentUserId());
            })
            ->limit(5000)
            ->get()
            ->map(function (Note $node) {
                return $this->mapGraphNode($node);
            });
    }

    /**
     * 构建图谱链接数据
     */
    private function buildGraphLinks()
    {
        $userId = $this->getCurrentUserId();

        // 只返回当前用户可见的笔记之间的链接
        // 可见笔记 = 用户自己的笔记 OR wiki 笔记
        return NoteLink::with(['sourceNote', 'targetNote'])
            ->where(function ($query) use ($userId) {
                $query->whereHas('sourceNote', function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('is_wiki', true);
                });
            })
            ->where(function ($query) use ($userId) {
                $query->whereHas('targetNote', function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('is_wiki', true);
                });
            })
            ->limit(5000)
            ->get()
            ->map(function (NoteLink $link) {
                return $this->mapGraphLink($link);
            })
            ->filter()
            ->values(); // 过滤掉 null 并重新索引，保证返回数组
    }

    /**
     * 映射节点模型为图谱节点结构
     */
    private function mapGraphNode(Note $node): array
    {
        return [
            'id' => $node->id,
            'title' => $node->title,
            'slug' => $node->slug,
            'tags' => $node->tags->pluck('name')->toArray(),
            'summary' => $node->summary ?? '',
        ];
    }

    /**
     * 映射链接模型为图谱链接结构
     */
    private function mapGraphLink(NoteLink $link): ?array
    {
        // 检查关联的节点是否存在，如果不存在则跳过该链接
        if (! $link->sourceNote || ! $link->targetNote) {
            return null;
        }

        return [
            'id' => $link->id,
            'source' => $link->sourceNote->id,
            'target' => $link->targetNote->id,
            'type' => $link->type,
        ];
    }

    /**
     * 映射 wiki 文章模型为输出结构
     */
    private function mapWikiArticle(Note $note): array
    {
        return [
            'title' => $note->title,
            'slug' => $note->slug,
            'content' => $note->content,
            'content_markdown' => $note->content_markdown,
        ];
    }

    /**
     * 准备笔记数据
     */
    private function prepareNoteData(array $input): array
    {
        $content = $input['content'] ?? '';
        $contentMarkdown = $input['content_markdown'] ?? '';

        // 如果提供了 JSON 格式的 content 但没有 markdown，尝试从 JSON 中提取文本作为 markdown
        if (empty($contentMarkdown)) {
            $contentMarkdown = $this->noteContentService->deriveMarkdownFromContent($content);
        }

        $data = [
            'title' => $input['title'] ?? '',
            'content' => $content,
            'content_markdown' => $contentMarkdown,
            'is_draft' => $input['is_draft'] ?? false,
        ];

        // 处理 wiki 相关字段（is_wiki 仅管理员可设置）
        $isAdmin = $this->currentUserIsAdmin();
        $wikiFields = ['slug', 'summary'];
        foreach ($wikiFields as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }

        if (array_key_exists('is_wiki', $input)) {
            if ($isAdmin) {
                $data['is_wiki'] = (bool) $input['is_wiki'];
            } elseif ($input['is_wiki']) {
                abort(403, '仅管理员可将笔记设为 Wiki');
            }
        }

        // 如果没有提供 slug 且是 wiki 节点，从 title 生成
        if (($data['is_wiki'] ?? false) && empty($data['slug'])) {
            $data['slug'] = Note::normalizeSlug($data['title']);
            $data['slug'] = Note::ensureUniqueSlug($data['slug']);
        }

        return $data;
    }

    /**
     * 对更新后的数据进行后处理(空内容、派生 markdown、wiki slug)
     */
    private function prepareUpdateData(array $validatedData, UpdateNoteRequest $request, Note $note): array
    {
        // 处理内容为空的情况(处理 Middleware 转换空字符串为 null 的情况)
        if ($request->has('content') && is_null($validatedData['content'] ?? null)) {
            $validatedData['content'] = '';
        }

        // 如果更新了内容但没有提供 markdown，尝试自动生成
        if (isset($validatedData['content']) && ! isset($validatedData['content_markdown'])) {
            $validatedData['content_markdown'] = $this->noteContentService->deriveMarkdownFromContent($validatedData['content']);
        }

        if (array_key_exists('is_wiki', $validatedData)) {
            if (! $this->currentUserIsAdmin()) {
                if ($validatedData['is_wiki'] && ! $note->is_wiki) {
                    abort(403, '仅管理员可将笔记设为 Wiki');
                }
                // 非管理员不可改 is_wiki
                unset($validatedData['is_wiki']);
            }
        }

        // 如果更新了 title 但没有提供 slug，且是 wiki 节点，重新生成 slug
        $isWiki = $validatedData['is_wiki'] ?? $note->is_wiki;
        if (isset($validatedData['title']) && ! isset($validatedData['slug']) && $isWiki) {
            $validatedData['slug'] = Note::normalizeSlug($validatedData['title']);
            $validatedData['slug'] = Note::ensureUniqueSlug($validatedData['slug'], $note->id);
        }

        return $validatedData;
    }

    /**
     * 创建链接
     */
    public function storeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:notes,id',
            'target_id' => 'required|exists:notes,id|different:source_id',
            'type' => 'nullable|string|max:255',
        ]);

        $userId = $this->getCurrentUserId();

        // 校验调用者对 source 和 target 笔记的所有权
        $sourceNote = Note::where('id', $validated['source_id'])
            ->where('user_id', $userId)
            ->first();
        $targetNote = Note::where('id', $validated['target_id'])
            ->where('user_id', $userId)
            ->first();

        if (! $sourceNote || ! $targetNote) {
            return $this->error('You do not have permission to create this link', [], 403);
        }

        // 检查是否已存在相同的链接
        $existingLink = NoteLink::where('source_id', $validated['source_id'])
            ->where('target_id', $validated['target_id'])
            ->first();

        if ($existingLink) {
            return $this->error('Link already exists', [], 422);
        }

        $link = NoteLink::create($validated);

        return $this->success([
            'link' => [
                'id' => $link->id,
                'source' => $link->source_id,
                'target' => $link->target_id,
                'type' => $link->type,
            ],
        ], 'Link created successfully', 201);
    }

    /**
     * 删除链接
     */
    public function destroyLink(int $id): JsonResponse
    {
        $link = NoteLink::findOrFail($id);
        $userId = $this->getCurrentUserId();

        // 校验调用者至少拥有 source 或 target 笔记之一
        $ownsSource = Note::where('id', $link->source_id)
            ->where('user_id', $userId)
            ->exists();
        $ownsTarget = Note::where('id', $link->target_id)
            ->where('user_id', $userId)
            ->exists();

        if (! $ownsSource && ! $ownsTarget) {
            return $this->error('You do not have permission to delete this link', [], 403);
        }

        $link->delete();

        return $this->success([], 'Link deleted successfully');
    }
}
