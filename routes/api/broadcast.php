<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Broadcast auth endpoint lives under /api because this file is loaded by routes/api.php.
Route::options('/broadcasting/auth', fn () => response()->json([], 200));

Route::post('/broadcasting/auth', function (Request $request) {
    Log::info('Broadcast auth attempt', [
        'channel' => $request->input('channel_name'),
        'socket_id' => $request->input('socket_id'),
        'has_auth' => $request->hasHeader('Authorization'),
        'user_id' => optional($request->user('sanctum'))->id,
    ]);

    // Let Laravel generate the correct pusher/reverb signed response.
    return Broadcast::auth($request);
})->middleware('auth:sanctum');
