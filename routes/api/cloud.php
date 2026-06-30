<?php

use App\Http\Controllers\Api\Cloud\FileController;
use App\Http\Controllers\Api\Cloud\FileTreeController;
use Illuminate\Support\Facades\Route;

// 文件原始内容（私有盘）：通过短期签名 URL 访问，
// 位于认证组之外以便 <img>/<iframe> 直接加载，由 signed 中间件校验签名
Route::get('/cloud/files/{id}/raw', [FileController::class, 'raw'])
    ->middleware('signed')
    ->name('cloud.files.raw');

// 云存储（需要认证）
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cloud/files', [FileController::class, 'index']);
    Route::get('/cloud/files/{id}', [FileController::class, 'show']);
    Route::post('/cloud/folders', [FileController::class, 'createFolder']);
    Route::post('/cloud/files', [FileController::class, 'upload']);
    Route::get('/cloud/files/{id}/download', [FileController::class, 'download'])->name('cloud.files.download');
    Route::get('/cloud/files/{id}/preview', [FileController::class, 'preview']);
    Route::delete('/cloud/files/{id}', [FileController::class, 'destroy']);
    Route::match(['put', 'patch'], '/cloud/files/{id}', [FileController::class, 'update']);
    Route::post('/cloud/files/move', [FileController::class, 'move']);

    // 树形结构和统计
    Route::get('/cloud/tree', [FileTreeController::class, 'tree']);
    Route::get('/cloud/statistics', [FileTreeController::class, 'statistics']);
});
