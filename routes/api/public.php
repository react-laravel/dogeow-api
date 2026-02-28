<?php

use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);

// GitHub OAuth
Route::get('/auth/github', [App\Http\Controllers\Api\GithubController::class, 'redirect']);
Route::get('/auth/github/callback', [App\Http\Controllers\Api\GithubController::class, 'callback']);

// Web Push：VAPID 公钥（公开，供前端订阅使用）
Route::get('/webpush/vapid', [App\Http\Controllers\Api\WebPushController::class, 'vapidKey']);

// Client info
Route::get('/client-basic-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getBasicInfo']);
Route::get('/client-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getClientInfo']);
Route::get('/client-location-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getLocationInfo']);

// System status (public, for /about/site)
Route::get('/system/status', [App\Http\Controllers\Api\SystemStatusController::class, 'index']);

// Cloud
require base_path('routes/api/cloud.php');

// Musics
Route::prefix('musics')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MusicController::class, 'index']);
    Route::get('/{filename}', [App\Http\Controllers\Api\MusicController::class, 'download']);
});

// Public notes
Route::get('notes/article/{slug}', [\App\Http\Controllers\Api\Note\NoteController::class, 'getArticleBySlug']);
Route::get('notes/wiki/articles', [\App\Http\Controllers\Api\Note\NoteController::class, 'getAllWikiArticles']);

// Vision AI 图片上传
Route::post('/vision/upload', [\App\Http\Controllers\Api\VisionUploadController::class, 'upload']);

// Public nav/tools
require base_path('routes/api/nav.php');
require base_path('routes/api/tools.php');
