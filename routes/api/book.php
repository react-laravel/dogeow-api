<?php

use App\Http\Controllers\Api\Book\BookMarkController;
use Illuminate\Support\Facades\Route;

Route::prefix('books/{book}/marks')->group(function () {
    Route::get('/', [BookMarkController::class, 'index']);
    Route::post('/', [BookMarkController::class, 'store']);
    Route::delete('/{mark}', [BookMarkController::class, 'destroy']);
});
