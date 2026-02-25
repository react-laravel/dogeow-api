<?php

use App\Models\Game\GameCharacter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// 广播认证路由 - 支持公共和私有频道
// OPTIONS 请求预检（CORS）
Route::options('/broadcasting/auth', function () {
    return response()->json([], 200);
});

// POST 请求用于实际认证
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    $channelName = $request->input('channel_name');
    $socketId = $request->input('socket_id');

    Log::info('Broadcast auth attempt', [
        'channel' => $channelName,
        'socket_id' => $socketId,
        'has_auth' => $request->hasHeader('Authorization'),
    ]);

    // 如果是公共频道（不以 private- 或 presence- 开头），允许访问
    if (! str_starts_with($channelName, 'private-') && ! str_starts_with($channelName, 'presence-')) {
        return response()->json(['auth' => 'public']);
    }

    // 对于私有频道，需要认证
    if (! auth('sanctum')->check()) {
        Log::warning('Broadcast auth failed: not authenticated', [
            'channel' => $channelName,
        ]);

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $user = auth('sanctum')->user();

    // 用户私有通知频道权限检查: user.{userId}.notifications / user.{userId}
    if (preg_match('/^private-user\.(\d+)(?:\.notifications)?$/', $channelName, $matches)) {
        $targetUserId = (int) $matches[1];

        if ((int) $user->id !== $targetUserId) {
            Log::warning('Broadcast auth failed: user channel forbidden', [
                'channel' => $channelName,
                'user_id' => $user->id,
                'target_user_id' => $targetUserId,
            ]);

            return response()->json(['error' => 'Forbidden'], 403);
        }

        Log::info('Broadcast auth success', [
            'channel' => $channelName,
            'user_id' => $user->id,
        ]);
    }

    // 游戏频道权限检查: game.{characterId}
    if (preg_match('/^private-game\.(\d+)$/', $channelName, $matches)) {
        $characterId = (int) $matches[1];

        // 检查角色是否属于当前用户
        $character = GameCharacter::where('id', $characterId)
            ->where('user_id', $user->id)
            ->first();

        if (! $character) {
            Log::warning('Broadcast auth failed: character not owned', [
                'channel' => $channelName,
                'user_id' => $user->id,
                'character_id' => $characterId,
            ]);

            return response()->json(['error' => 'Forbidden'], 403);
        }

        Log::info('Broadcast auth success', [
            'channel' => $channelName,
            'user_id' => $user->id,
            'character_id' => $characterId,
        ]);
    }

    // 认证成功
    return response()->json(['auth' => 'success']);
})->middleware(['web', 'auth:sanctum']); // 使用 web 中间件以支持 session 和 Sanctum 认证
