<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientInfoController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\GithubWebhookController;
use App\Http\Controllers\Api\MiniMaxController;
use App\Http\Controllers\Api\MusicController;
use App\Http\Controllers\Api\Note\NoteController;
use App\Http\Controllers\Api\SystemStatusController;
use App\Http\Controllers\Api\VisionUploadController;
use App\Http\Controllers\Api\WebPushController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// GitHub OAuth
Route::get('/auth/github', [GithubController::class, 'redirect']);
Route::get('/auth/github/callback', [GithubController::class, 'callback']);
Route::post('/github/webhooks/repo-watch', [GithubWebhookController::class, 'repoWatch']);

// Web Push：VAPID 公钥(公开，供前端订阅使用)
Route::get('/webpush/vapid', [WebPushController::class, 'vapidKey']);

// Client info
Route::get('/client-basic-info', [ClientInfoController::class, 'getBasicInfo']);
Route::get('/client-info', [ClientInfoController::class, 'getClientInfo']);
Route::get('/client-location-info', [ClientInfoController::class, 'getLocationInfo']);

// System status (public, for /about/site)
Route::get('/system/status', [SystemStatusController::class, 'index']);

// Cloud
require base_path('routes/api/cloud.php');

// Musics
Route::prefix('musics')->group(function () {
    Route::get('/', [MusicController::class, 'index']);
    Route::get('/lyrics/{filename}', [MusicController::class, 'lyrics']);
    Route::get('/{filename}', [MusicController::class, 'download']);
});

// Public notes
Route::get('notes/article/{slug}', [NoteController::class, 'getArticleBySlug']);
Route::get('notes/wiki/articles', [NoteController::class, 'getAllWikiArticles']);

// Vision AI 图片上传
Route::post('/vision/upload', [VisionUploadController::class, 'upload']);

// Public nav/tools
require base_path('routes/api/nav.php');
require base_path('routes/api/tools.php');

// MiniMax
Route::post('/minimax/roleplay', [MiniMaxController::class, 'roleplayChat'])->middleware(
    'throttle:12,1'
);
