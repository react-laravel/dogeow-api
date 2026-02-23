<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// 广播认证路由 - 必须在认证中间件外部，但内部会检查 Sanctum 认证
require base_path('routes/api/broadcast.php');

// 公开路由
require base_path('routes/api/public.php');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'update']);

    // WebSocket authentication test route
    Route::middleware('websocket.auth')->get('/websocket-test', function () {
        return response()->json([
            'message' => 'WebSocket authentication successful',
            'user' => auth()->user()->only(['id', 'name', 'email']),
        ]);
    });

    // 批量上传图片
    Route::post('/upload/images', [UploadController::class, 'uploadBatchImages']);

    // 引入各个项目的路由文件
    require base_path('routes/api/chat.php'); // 聊天室
    require base_path('routes/api/game.php'); // 游戏
    require base_path('routes/api/item.php'); // 物品
    require base_path('routes/api/location.php'); // 地点
    require base_path('routes/api/note.php'); // 笔记
    require base_path('routes/api/profile.php'); // 个人资料
    require base_path('routes/api/todo.php'); // 待办事项
    require base_path('routes/api/word.php'); // 单词
    require base_path('routes/api/logs.php'); // 日志

});
