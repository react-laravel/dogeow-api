<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Broadcast auth endpoint lives under /api because this file is loaded by routes/api.php.
// CORS preflight: return 200 with proper headers so browser allows the actual POST.
Route::options('/broadcasting/auth', function (Request $request) {
    return response()->json([], 200);
});

Route::post('/broadcasting/auth', function (Request $request) {
    $user = $request->user('sanctum');

    Log::info('Broadcast auth attempt', [
        'channel' => $request->input('channel_name'),
        'socket_id' => $request->input('socket_id'),
        'has_auth' => $request->hasHeader('Authorization'),
        'user_id' => $user?->id,
    ]);

    if (! $user) {
        Log::warning('Broadcast auth failed: unauthenticated', [
            'channel' => $request->input('channel_name'),
            'has_auth' => $request->hasHeader('Authorization'),
        ]);

        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    // Let Laravel generate the correct pusher/reverb signed response.
    return Broadcast::auth($request);
})->middleware('auth:sanctum');
