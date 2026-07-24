<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Dashboard\ClientInfoController;
use App\Http\Controllers\Api\Dashboard\MusicController;
use App\Http\Controllers\Api\Dashboard\WebPushController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\Note\NoteController;
use App\Http\Controllers\Api\SsoController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
Route::post('/auth/sso/exchange', [SsoController::class, 'exchange'])->middleware('throttle:60,1');

// GitHub OAuth
Route::get('/auth/github', [GithubController::class, 'redirect']);
Route::get('/auth/github/callback', [GithubController::class, 'callback']);
Route::post('/auth/github/callback', [GithubController::class, 'exchange']);

// Web Push：VAPID 公钥(公开，供前端订阅使用)
Route::get('/webpush/vapid', [WebPushController::class, 'vapidKey']);

// Client info
Route::get('/client-basic-info', [ClientInfoController::class, 'getBasicInfo']);
Route::get('/client-info', [ClientInfoController::class, 'getClientInfo']);
Route::get('/client-location-info', [ClientInfoController::class, 'getLocationInfo']);

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

// Public nav/tools
require base_path('routes/api/nav.php');
require base_path('routes/api/tools.php');
