<?php

use App\Http\Controllers\Api\Thing\CategoryController;
use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Thing\ItemSearchController;
use App\Http\Controllers\Api\Thing\TagController;
use Illuminate\Support\Facades\Route;

// 物品
Route::prefix('things')->name('things.')->group(function () {
    Route::get('items/categories', [ItemController::class, 'categories'])->name('items.categories');
    Route::apiResource('items', ItemController::class);

    // 搜索相关路由
    Route::get('search', [ItemSearchController::class, 'search'])->name('items.search');
    Route::get('search/suggestions', [ItemSearchController::class, 'searchSuggestions'])->name('items.search.suggestions');
    Route::get('search/history', [ItemSearchController::class, 'searchHistory'])->name('items.search.history');
    Route::delete('search/history', [ItemSearchController::class, 'clearSearchHistory'])->name('items.search.history.clear');

    // 物品关联路由
    Route::get('items/{item}/relations', [ItemController::class, 'relations'])->name('items.relations');
    Route::post('items/{item}/relations', [ItemController::class, 'addRelation'])->name('items.relations.add');
    Route::delete('items/{item}/relations/{relatedItemId}', [ItemController::class, 'removeRelation'])->name('items.relations.remove');
    Route::post('items/{item}/relations/batch', [ItemController::class, 'batchAddRelations'])->name('items.relations.batch');

    // 分类
    Route::apiResource('categories', CategoryController::class);

    // 标签
    Route::apiResource('tags', TagController::class);
});
