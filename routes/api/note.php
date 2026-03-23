<?php

use Illuminate\Support\Facades\Route;

// 笔记相关路由
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('notes/tags', \App\Http\Controllers\Api\Note\NoteTagController::class);
    Route::apiResource('notes/categories', \App\Http\Controllers\Api\Note\NoteCategoryController::class);

    // 图谱相关路由(必须在 apiResource 之前定义，避免被当作资源 ID)
    Route::get('notes/graph', [\App\Http\Controllers\Api\Note\NoteController::class, 'getGraph']);
    Route::post('notes/links', [\App\Http\Controllers\Api\Note\NoteController::class, 'storeLink']);
    Route::delete('notes/links/{id}', [\App\Http\Controllers\Api\Note\NoteController::class, 'destroyLink']);

    Route::apiResource('notes', \App\Http\Controllers\Api\Note\NoteController::class);
});

// 注意：公开路由需要在 routes/api.php 中定义，因为此文件被包含在 auth:sanctum 中间件组内
