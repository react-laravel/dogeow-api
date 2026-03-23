<?php

use App\Http\Controllers\Api\Thing\LocationAreaController;
use App\Http\Controllers\Api\Thing\LocationRoomController;
use App\Http\Controllers\Api\Thing\LocationSpotController;
use App\Http\Controllers\Api\Thing\LocationTreeController;
use Illuminate\Support\Facades\Route;

// 树形结构的位置数据
Route::get('locations/tree', [LocationTreeController::class, 'tree']);

// 区域
Route::get('areas', [LocationAreaController::class, 'index']);
Route::post('areas', [LocationAreaController::class, 'store']);
Route::get('areas/{area}', [LocationAreaController::class, 'show']);
Route::put('areas/{area}', [LocationAreaController::class, 'update']);
Route::delete('areas/{area}', [LocationAreaController::class, 'destroy']);
Route::get('areas/{area}/rooms', [LocationAreaController::class, 'rooms']);
Route::post('areas/{area}/set-default', [LocationAreaController::class, 'setDefault']);

// 房间
Route::get('rooms', [LocationRoomController::class, 'index']);
Route::post('rooms', [LocationRoomController::class, 'store']);
Route::get('rooms/{room}', [LocationRoomController::class, 'show']);
Route::put('rooms/{room}', [LocationRoomController::class, 'update']);
Route::delete('rooms/{room}', [LocationRoomController::class, 'destroy']);
Route::get('rooms/{room}/spots', [LocationRoomController::class, 'spots']);

// 具体位置
Route::get('spots', [LocationSpotController::class, 'index']);
Route::post('spots', [LocationSpotController::class, 'store']);
Route::get('spots/{spot}', [LocationSpotController::class, 'show']);
Route::put('spots/{spot}', [LocationSpotController::class, 'update']);
Route::delete('spots/{spot}', [LocationSpotController::class, 'destroy']);
