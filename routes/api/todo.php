<?php

use App\Http\Controllers\Api\Todo\TodoListController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('todos')->group(function () {
    Route::get('/', [TodoListController::class, 'index']);
    Route::post('/', [TodoListController::class, 'store']);
    Route::get('/{id}', [TodoListController::class, 'show']);
    Route::put('/{id}', [TodoListController::class, 'update']);
    Route::delete('/{id}', [TodoListController::class, 'destroy']);

    Route::post('/{id}/tasks', [TodoListController::class, 'storeTask']);
    Route::put('/{id}/tasks/reorder', [TodoListController::class, 'reorderTasks']);
    Route::patch('/{id}/tasks/{taskId}', [TodoListController::class, 'updateTask']);
    Route::delete('/{id}/tasks/{taskId}', [TodoListController::class, 'destroyTask']);
});
