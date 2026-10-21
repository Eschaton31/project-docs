<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

// The Vue SPA handles every other GET path (vue-router uses HTML5 history mode).
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api/|broadcasting/|sanctum/|up$).*$');
