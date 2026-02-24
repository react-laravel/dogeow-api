<?php

use App\Http\Controllers\Api\Chat\ChatController;
use App\Http\Controllers\Api\Chat\ChatModerationController;
use Illuminate\Support\Facades\Route;

Route::prefix('chat')->group(function () {
    // Room management endpoints
    Route::get('/rooms', [ChatController::class, 'getRooms']);
    Route::post('/rooms', [ChatController::class, 'createRoom']);
    Route::put('/rooms/{roomId}', [ChatController::class, 'updateRoom']);
    Route::post('/rooms/{roomId}/join', [ChatController::class, 'joinRoom']);
    Route::post('/rooms/{roomId}/leave', [ChatController::class, 'leaveRoom']);
    Route::delete('/rooms/{roomId}', [ChatController::class, 'deleteRoom']);

    // Message handling endpoints
    Route::get('/rooms/{roomId}/messages', [ChatController::class, 'getMessages']);
    Route::post('/rooms/{roomId}/messages', [ChatController::class, 'sendMessage']);
    Route::delete('/rooms/{roomId}/messages/{messageId}', [ChatController::class, 'deleteMessage']);

    // User presence management endpoints
    Route::get('/rooms/{roomId}/users', [ChatController::class, 'getOnlineUsers']);
    Route::post('/rooms/{roomId}/status', [ChatController::class, 'updateUserStatus']);
    Route::get('/rooms/{roomId}/my-status', [ChatController::class, 'getUserPresenceStatus']);
    Route::post('/cleanup-disconnected', [ChatController::class, 'cleanupDisconnectedUsers']);

    // Moderation endpoints (admin/moderator only)
    Route::prefix('moderation')->group(function () {
        // Message moderation
        Route::delete('/rooms/{roomId}/messages/{messageId}', [ChatModerationController::class, 'deleteMessage']);

        // User moderation
        Route::post('/rooms/{roomId}/users/{userId}/mute', [ChatModerationController::class, 'muteUser']);
        Route::post('/rooms/{roomId}/users/{userId}/unmute', [ChatModerationController::class, 'unmuteUser']);
        Route::post('/rooms/{roomId}/users/{userId}/ban', [ChatModerationController::class, 'banUser']);
        Route::post('/rooms/{roomId}/users/{userId}/unban', [ChatModerationController::class, 'unbanUser']);

        // Moderation logs and status
        Route::get('/rooms/{roomId}/actions', [ChatModerationController::class, 'getModerationActions']);
        Route::get('/rooms/{roomId}/users/{userId}/status', [ChatModerationController::class, 'getUserModerationStatus']);
    });

    // Reporting endpoints
    Route::prefix('reports')->group(function () {
        // Report messages
        Route::post('/rooms/{roomId}/messages/{messageId}', [App\Http\Controllers\Api\Chat\ChatReportController::class, 'reportMessage']);

        // View reports (moderators only)
        Route::get('/rooms/{roomId}', [App\Http\Controllers\Api\Chat\ChatReportController::class, 'getRoomReports']);
        Route::get('/', [App\Http\Controllers\Api\Chat\ChatReportController::class, 'getAllReports']);

        // Review reports (moderators only)
        Route::post('/{reportId}/review', [App\Http\Controllers\Api\Chat\ChatReportController::class, 'reviewReport']);

        // Report statistics
        Route::get('/stats', [App\Http\Controllers\Api\Chat\ChatReportController::class, 'getReportStats']);
    });
});
