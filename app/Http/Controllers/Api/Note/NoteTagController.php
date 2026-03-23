<?php

namespace App\Http\Controllers\Api\Note;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteTagRequest;
use App\Models\Note\NoteTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NoteTagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tags = NoteTag::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tags);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NoteTagRequest $request): JsonResponse
    {
        $tag = NoteTag::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'color' => $request->color ?? '#3b82f6',
        ]);

        return response()->json($tag, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $tag = NoteTag::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json($tag);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(NoteTagRequest $request, string $id): JsonResponse
    {
        $tag = NoteTag::where('user_id', Auth::id())
            ->findOrFail($id);

        $tag->update($request->validated());

        return response()->json($tag);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $tag = NoteTag::where('user_id', Auth::id())
            ->findOrFail($id);

        // 先删除关联关系
        $tag->notes()->detach();

        $tag->delete();

        return response()->json(null, 204);
    }
}
