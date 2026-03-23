<?php

use App\Http\Controllers\Api\Word\BookController;
use App\Http\Controllers\Api\Word\CheckInController;
use App\Http\Controllers\Api\Word\LearningController;
use App\Http\Controllers\Api\Word\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('word')->name('word.')->group(function () {
    // 单词书
    Route::get('books', [BookController::class, 'index']);
    Route::get('books/{id}', [BookController::class, 'show']);
    Route::get('books/{id}/words', [BookController::class, 'words']);

    // 学习
    Route::get('daily', [LearningController::class, 'getDailyWords']);
    Route::get('review', [LearningController::class, 'getReviewWords']);
    Route::get('fill-blank', [LearningController::class, 'getFillBlankWords']);
    Route::post('mark/{id}', [LearningController::class, 'markWord']);
    Route::post('simple/{id}', [LearningController::class, 'markWordAsSimple']);
    Route::get('progress', [LearningController::class, 'getProgress']);

    // 搜索和创建单词
    Route::get('search/{keyword}', [LearningController::class, 'searchWord']);
    Route::post('create', [LearningController::class, 'createWord']);

    // 单词管理
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
