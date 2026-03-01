<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WebPushController;
use Illuminate\Support\Facades\Route;

// Web Push：保存/删除当前用户的推送订阅
Route::post('/user/push-subscription', [WebPushController::class, 'updateSubscription']);
Route::delete('/user/push-subscription', [WebPushController::class, 'deleteSubscription']);

// 未读通知（含打开时补发汇总推送）
Route::get('/notifications/unread', [NotificationController::class, 'unread']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
