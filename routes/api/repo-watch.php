<?php

use App\Http\Controllers\Api\RepositoryWatchController;
use Illuminate\Support\Facades\Route;

Route::prefix('repo-watch')->group(function () {
    Route::post('/preview', [RepositoryWatchController::class, 'preview']);
    Route::get('/packages', [RepositoryWatchController::class, 'index']);
    Route::post('/packages', [RepositoryWatchController::class, 'store']);
    Route::delete('/packages', [RepositoryWatchController::class, 'destroyBatch']);
    Route::post('/packages/{watchedPackage}/refresh', [RepositoryWatchController::class, 'refresh']);
    Route::delete('/packages/{watchedPackage}', [RepositoryWatchController::class, 'destroy']);
});
