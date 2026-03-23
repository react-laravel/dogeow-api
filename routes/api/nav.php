<?php

use App\Http\Controllers\Api\Nav\CategoryController;
use App\Http\Controllers\Api\Nav\ItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('nav')->group(function () {
    Route::get('items', [ItemController::class, 'index'])->name('nav.items.index');
    Route::get('items/{item}', [ItemController::class, 'show'])->name('nav.items.show');
    Route::post('items', [ItemController::class, 'store'])->name('nav.items.store');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('nav.items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('nav.items.destroy');
    Route::post('items/{item}/click', [ItemController::class, 'recordClick'])->name('nav.items.click');

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/all', [CategoryController::class, 'all']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function () {
    // 导航管理相关路由需要认证
    Route::prefix('nav')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });
});
