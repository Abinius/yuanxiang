<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\DashboardController;
use Illuminate\Support\Facades\Route;

// 平台后台（SaaS 管理）
Route::prefix('platform')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('platform.login');
    Route::post('/login', [AuthController::class, 'login'])->name('platform.login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('platform.logout');

    Route::middleware(['auth', 'role:platform_admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('platform.dashboard');
    });
});
