<?php

use App\Http\Controllers\Api\Dashboard\CacheController;
use Illuminate\Support\Facades\Route;

Route::middleware(['admin'])->group(function () {
    Route::get('/cache', [CacheController::class, 'index']);
    Route::delete('/cache/{id}', [CacheController::class, 'destroy']);
});
