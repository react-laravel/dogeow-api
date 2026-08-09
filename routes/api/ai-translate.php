<?php

use App\Http\Controllers\Api\AiTranslate\WordSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai-translate')->name('ai-translate.')->group(function () {
    Route::get('words', [WordSyncController::class, 'show']);
    Route::post('words/sync', [WordSyncController::class, 'sync'])->middleware('throttle:30,1');
    Route::put('words', [WordSyncController::class, 'replace'])->middleware('throttle:20,1');
});
