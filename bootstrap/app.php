<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        using: function () {
            // 健康检查（不挂 web 组，避免 session/CSRF 依赖）
            Route::get('/up', fn () => response()->json(['status' => 'ok']));
            Route::middleware('web')->group(base_path('routes/web.php'));
            Route::middleware('web')->group(base_path('routes/platform.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant.member' => \App\Http\Middleware\TenantMemberMiddleware::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // 微信支付回调由微信侧验签，跳过 CSRF
        $middleware->validateCsrfTokens(except: ['pay/wechat/notify']);

        // 未登录访问受保护路由：/t/{slug}/* 跳该租户登录，其余（platform/*）跳平台登录
        $middleware->redirectGuestsTo(function (Request $request) {
            if (preg_match('#^t/([^/]+)#', $request->path(), $m)) {
                return route('tenant.login', ['tenant' => $m[1]]);
            }

            return route('platform.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
