<?php

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit']);
    Route::match(['put', 'patch'], '/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});
