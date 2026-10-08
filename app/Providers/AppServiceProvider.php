<?php

namespace App\Providers;

use App\Support\Tenant;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 单租户：全站视图共享 $tenant（替代原路由参数注入）
        View::composer('*', fn ($view) => $view->with('tenant', Tenant::current()));
    }
}
