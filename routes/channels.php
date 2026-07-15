<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 用户私有频道，用于发送通知
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// 用户通知私有频道(如：user.1.notifications)
Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// 用户上传/去背景私有频道(如：user.1.uploads)
Broadcast::channel('user.{userId}.uploads', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
