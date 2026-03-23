<?php

use App\Http\Controllers\Api\LogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/logs', [LogController::class, 'index']);
    Route::get('/logs/show', [LogController::class, 'show']);
    Route::post('/logs/notify', [LogController::class, 'notify']);
});
