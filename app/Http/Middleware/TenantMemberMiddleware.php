<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 租户成员中间件：已登录用户必须属于当前路由租户（防跨租户越权）。
 *
 * 挂载在 /t/{slug} 下 auth-only 的前台资源路由：/live、/adopt 下单支付链、/my、礼盒、续费、推荐。
 * 公开页（首页/认养浏览/溯源/扫码/礼盒落地/短链/公开铭牌）不挂——保持跨租户公开可访问（分享/SEO 场景）。
 * 会话路由（logout / bind-phone）不挂——避免锁死后无法登出或绑定。
 * 后台/家人端由 role 中间件做同等校验（RoleMiddleware），无需叠加。
 *
 * 与 TenantMiddleware 分工：TenantMiddleware 只解析 slug 并注入上下文；
 * 本中间件校验「当前用户是否属于该租户」——前者设上下文，后者防越权。
 */
class TenantMemberMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (TenantContext::has()
            && Auth::check()
            && (int) Auth::user()->tenant_id !== TenantContext::id()
        ) {
            abort(403, '无权访问该村庄');
        }

        return $next($request);
    }
}
