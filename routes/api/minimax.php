<?php

use App\Http\Controllers\Api\MiniMaxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/minimax/subscription', [MiniMaxController::class, 'subscription']);
    Route::get('/minimax/subscription-detail', [MiniMaxController::class, 'subscriptionDetail']);
    Route::get('/minimax/billing', [MiniMaxController::class, 'billing']);
});
