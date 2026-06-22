<?php

use App\Http\Controllers\Api\Dashboard\HomeLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware(['admin'])->group(function () {
    Route::get('/dashboard/home-links', [HomeLinkController::class, 'index']);
});
