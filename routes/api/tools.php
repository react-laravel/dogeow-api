<?php

use App\Http\Controllers\Api\TitleController;
use Illuminate\Support\Facades\Route;

Route::get('/fetch-title', [TitleController::class, 'fetch']);
