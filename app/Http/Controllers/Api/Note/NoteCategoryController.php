<?php

namespace App\Http\Controllers\Api\Note;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteCategoryRequest;
use App\Models\Note\NoteCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NoteCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $categories = NoteCategory::where('user_id', Auth::id())
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NoteCategoryRequest $request): JsonResponse
    {
        $category = NoteCategory::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $category = NoteCategory::where('user_id', Auth::id())
            ->with('notes')
            ->findOrFail($id);

        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(NoteCategoryRequest $request, string $id): JsonResponse
    {
        $category = NoteCategory::where('user_id', Auth::id())
            ->findOrFail($id);

        $category->update($request->validated());

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = NoteCategory::where('user_id', Auth::id())
            ->findOrFail($id);

        // 分类下的笔记会自动设置分类为 null(由于外键约束 nullOnDelete)
        $category->delete();

        return response()->json(null, 204);
    }
}
