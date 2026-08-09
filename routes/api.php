<?php

use App\Http\Controllers\Api\Ai\VisionUploadController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SsoController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// 公开路由
require base_path('routes/api/public.php');

// 广播认证路由 - 支持公共和私有频道(需要在 auth:sanctum 组外以便处理公共频道)
require base_path('routes/api/broadcast.php');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/sso/ticket', [SsoController::class, 'issue'])->middleware('throttle:30,1');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'update']);

    // 批量上传图片
    Route::get('/upload/images/rmbg-status', [UploadController::class, 'rmbgStatus']);
    Route::post('/upload/images', [UploadController::class, 'uploadBatchImages'])->middleware('throttle:20,1');

    // Vision AI 图片上传（需登录）
    Route::post('/vision/upload', [VisionUploadController::class, 'upload'])
        ->middleware('throttle:5,1');

    // 引入各个项目的路由文件
    require base_path('routes/api/notification.php'); // Web Push + 通知
    require base_path('routes/api/websocket.php'); // WebSocket
    require base_path('routes/api/item.php'); // 物品
    require base_path('routes/api/location.php'); // 地点
    require base_path('routes/api/note.php'); // 笔记
    require base_path('routes/api/profile.php'); // 个人资料
    require base_path('routes/api/word.php'); // 单词
    require base_path('routes/api/book.php'); // 书籍
    require base_path('routes/api/todo.php'); // 待办
    require base_path('routes/api/logs.php'); // 日志
    require base_path('routes/api/cache.php'); // 缓存管理
    require base_path('routes/api/dashboard.php'); // Dashboard
    require base_path('routes/api/knowledge.php'); // 知识图谱
    require base_path('routes/api/ai-translate.php'); // 英语学习扩展单词云同步

});
