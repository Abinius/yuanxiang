<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Scheduling\Schedule;

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

        // F8 R8.2：全局 web 前置，把 ?ref=CODE 存进 session 供下单表单预填
        $middleware->web(\App\Http\Middleware\PreserveReferralCode::class);

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
    })
    ->withSchedule(function (Schedule $schedule): void {
        // F2 R2.1：每日 07:00 回收超期弃付单并释放田块
        $schedule->command('adoption:expire-pending')->dailyAt('07:00');
        // F4 R4.2：每日 06:00 推送"该发动态了"给 3 天未录的家人们
        $schedule->command('family:remind-post')->dailyAt('06:00');
        // F9：每日 08:00 续费到期提醒（30/7/1 天）+ auto_renew 临期自动建单
        $schedule->command('adoption:renewal-reminder')->dailyAt('08:00');
        // M4：每日 06:30 佣金冷却期结算（pending → available）
        $schedule->command('commission:settle')->dailyAt('06:30');
    })
    ->create();
