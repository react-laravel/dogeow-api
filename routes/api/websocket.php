<?php

use Illuminate\Support\Facades\Route;

// WebSocket authentication test route
Route::middleware('websocket.auth')->get('/websocket-test', function () {
    return response()->json([
        'message' => 'WebSocket authentication successful',
        'user' => auth()->user()->only(['id', 'name', 'email']),
    ]);
});
