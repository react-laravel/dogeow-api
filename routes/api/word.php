<?php

use App\Http\Controllers\Api\Word\BookController;
use App\Http\Controllers\Api\Word\CheckInController;
use App\Http\Controllers\Api\Word\LearningController;
use App\Http\Controllers\Api\Word\SettingController;
use Illuminate\Support\Facades\Route;

// 公开只读路由（单词书浏览）
Route::prefix('word')->name('word.')->group(function () {
    Route::get('books', [BookController::class, 'index']);
    Route::get('books/{id}', [BookController::class, 'show']);
    Route::get('books/{id}/words', [BookController::class, 'words']);
    Route::get('search/{keyword}', [LearningController::class, 'searchWord']);
});

// 需要认证的路由
Route::middleware('auth:sanctum')->prefix('word')->name('word.')->group(function () {
    // 学习相关（需要用户上下文）
    Route::get('daily', [LearningController::class, 'getDailyWords']);
    Route::get('review', [LearningController::class, 'getReviewWords']);
    Route::get('fill-blank', [LearningController::class, 'getFillBlankWords']);
    Route::post('mark/{id}', [LearningController::class, 'markWord']);
    Route::post('simple/{id}', [LearningController::class, 'markWordAsSimple']);
    Route::get('progress', [LearningController::class, 'getProgress']);

    // 单词管理
    Route::post('create', [LearningController::class, 'createWord']);
    Route::patch('{id}', [LearningController::class, 'updateWord']);

    // 打卡
    Route::post('check-in', [CheckInController::class, 'checkIn']);
    // 整年日历
    Route::get('calendar/year/{year}', [CheckInController::class, 'getCalendarYear']);
    // 最近 365 天
    Route::get('calendar/last365', [CheckInController::class, 'getCalendarLast365']);
    Route::get('calendar/{year}/{month}', [CheckInController::class, 'getCalendar']);
    Route::get('stats', [CheckInController::class, 'getStats']);

    // 设置
    Route::get('settings', [SettingController::class, 'show']);
    Route::put('settings', [SettingController::class, 'update']);
});
