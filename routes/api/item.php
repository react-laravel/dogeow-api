<?php

use App\Http\Controllers\Api\Thing\CategoryController;
use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Thing\ItemSearchController;
use App\Http\Controllers\Api\Thing\TagController;
use Illuminate\Support\Facades\Route;

// 物品（公开只读）
Route::prefix('things')->name('things.')->group(function () {
    Route::get('items/categories', [ItemController::class, 'categories'])->name('items.categories');
    Route::get('items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('items', [ItemController::class, 'index'])->name('items.index');

    // 搜索相关路由
    Route::get('search', [ItemSearchController::class, 'search'])->name('items.search');
    Route::get('search/suggestions', [ItemSearchController::class, 'searchSuggestions'])->name('items.search.suggestions');
    Route::get('search/history', [ItemSearchController::class, 'searchHistory'])->name('items.search.history');

    // 分类（公开只读）
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

    // 标签（公开只读）
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::get('tags/{tag}', [TagController::class, 'show'])->name('tags.show');
});

// 需要认证的路由
Route::middleware('auth:sanctum')->prefix('things')->name('things.')->group(function () {
    // 物品写操作
    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

    // 搜索历史管理
    Route::delete('search/history', [ItemSearchController::class, 'clearSearchHistory'])->name('items.search.history.clear');

    // 物品关联路由
    Route::get('items/{item}/relations', [ItemController::class, 'relations'])->name('items.relations');
    Route::post('items/{item}/relations', [ItemController::class, 'addRelation'])->name('items.relations.add');
    Route::delete('items/{item}/relations/{relatedItemId}', [ItemController::class, 'removeRelation'])->name('items.relations.remove');
    Route::post('items/{item}/relations/batch', [ItemController::class, 'batchAddRelations'])->name('items.relations.batch');

    // 分类写操作
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // 标签写操作
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
});
