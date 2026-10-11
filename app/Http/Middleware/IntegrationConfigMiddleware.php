<?php

namespace App\Http\Middleware;

use App\Support\IntegrationConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 请求前置应用后台集成配置。挂全局 web，另在 /pay/wechat/notify 上单独挂一份
 * （该路由在 web 组之外，见 routes/web.php 顶部）。
 */
class IntegrationConfigMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        IntegrationConfig::apply();

        return $next($request);
    }
}
