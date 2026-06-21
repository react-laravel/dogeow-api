<?php

use App\Http\Controllers\Api\Knowledge\KnowledgeGraphController;
use Illuminate\Support\Facades\Route;

// 公开只读路由
Route::prefix('knowledge-graphs')->group(function () {
    Route::get('/', [KnowledgeGraphController::class, 'index']);
});

// 需要认证的路由
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('knowledge-graphs')->group(function () {
        Route::post('/', [KnowledgeGraphController::class, 'store']);
        Route::get('/{graph}', [KnowledgeGraphController::class, 'show']);
        Route::match(['put', 'patch'], '/{graph}', [KnowledgeGraphController::class, 'update']);
        Route::delete('/{graph}', [KnowledgeGraphController::class, 'destroy']);
    });
});
