<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// 公开路由
require base_path('routes/api/public.php');

// 广播认证路由 - 支持公共和私有频道(需要在 auth:sanctum 组外以便处理公共频道)
require base_path('routes/api/broadcast.php');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'update']);

    // 批量上传图片
    Route::post('/upload/images', [UploadController::class, 'uploadBatchImages']);

    // 引入各个项目的路由文件
    require base_path('routes/api/notification.php'); // Web Push + 通知
    require base_path('routes/api/websocket.php'); // WebSocket
    require base_path('routes/api/chat.php'); // 聊天室
    require base_path('routes/api/game.php'); // 游戏
    require base_path('routes/api/item.php'); // 物品
    require base_path('routes/api/location.php'); // 地点
    require base_path('routes/api/note.php'); // 笔记
    require base_path('routes/api/profile.php'); // 个人资料
    require base_path('routes/api/repo-watch.php'); // 仓库更新追踪
    require base_path('routes/api/word.php'); // 单词
    require base_path('routes/api/todo.php'); // 待办
    require base_path('routes/api/logs.php'); // 日志
    require base_path('routes/api/minimax.php'); // MiniMax

});
