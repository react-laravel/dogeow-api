<?php

use App\Http\Controllers\Api\Nav\CategoryController;
use App\Http\Controllers\Api\Nav\ItemController;
use Illuminate\Support\Facades\Route;

// 公开只读路由
Route::prefix('nav')->group(function () {
    Route::get('items', [ItemController::class, 'index'])->name('nav.items.index');
    Route::get('items/{item}', [ItemController::class, 'show'])->name('nav.items.show');
    Route::post('items/{item}/click', [ItemController::class, 'recordClick'])->name('nav.items.click');

    Route::get('/categories', [CategoryController::class, 'index']);
    // 必须在 {category} 之前注册，避免 "all" 被当成 ID
    Route::get('/categories/all', [CategoryController::class, 'all'])
        ->middleware(['auth:sanctum', 'admin']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
});

// 需要认证 + 管理员的写操作
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::prefix('nav')->group(function () {
        // 导航项管理
        Route::post('items', [ItemController::class, 'store'])->name('nav.items.store');
        Route::match(['put', 'patch'], 'items/{item}', [ItemController::class, 'update'])->name('nav.items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('nav.items.destroy');

        // 分类管理
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });
});
