<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 聊天房间频道，用于实时消息通信
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});

// 聊天房间的 presence 频道，用于实时跟踪在线用户状态
Broadcast::channel('chat.room.{roomId}.presence', function ($user, $roomId) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => $user->avatar ?? null,
    ];
});

// 用户私有频道，用于发送通知
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// 用户通知私有频道（如：user.1.notifications）
Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
