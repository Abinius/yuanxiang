<?php

use App\Http\Controllers\Platform\AuthController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\UserController as PlatformUsers;
use Illuminate\Support\Facades\Route;

// 平台后台（SaaS 管理）
Route::prefix('platform')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('platform.login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('platform.login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('platform.logout');

    Route::middleware(['auth', 'role:platform_admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('platform.dashboard');

        // 1.6 平台后台账号管理（商户管理员 + 平台管理员）
        Route::get('/users', [PlatformUsers::class, 'index'])->name('platform.users.index');
        Route::get('/users/create', [PlatformUsers::class, 'create'])->name('platform.users.create');
        Route::post('/users', [PlatformUsers::class, 'store'])->name('platform.users.store');
        Route::get('/users/{user}/edit', [PlatformUsers::class, 'edit'])->name('platform.users.edit');
        Route::put('/users/{user}', [PlatformUsers::class, 'update'])->name('platform.users.update');
        Route::post('/users/{user}/toggle', [PlatformUsers::class, 'toggle'])->name('platform.users.toggle');
        Route::post('/users/{user}/reset-password', [PlatformUsers::class, 'resetPassword'])->name('platform.users.reset-password');
    });
});
