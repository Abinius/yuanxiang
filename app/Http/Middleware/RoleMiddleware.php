<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 角色中间件：`role:tenant_admin` 或 `role:family,tenant_admin`。
 * 租户上下文内（/t/{slug} 下），租户级角色还必须属于当前租户，防跨租户越权。
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        abort_unless(Auth::check(), 401, '请先登录');

        $allowed = array_merge([], ...array_map(fn (string $r) => explode(',', $r), $roles));
        $user = $request->user();

        abort_unless(in_array($user->role->value, $allowed, true), 403, '无权限访问');

        if (TenantContext::has()
            && ! in_array('platform_admin', $allowed, true)
            && (int) $user->tenant_id !== TenantContext::id()
        ) {
            abort(403, '无权访问该村庄');
        }

        return $next($request);
    }
}
