<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 角色中间件：`role:tenant_admin` 或 `role:family,tenant_admin`。
 * 单租户下不再做跨租户校验，仅按 role 放行。
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        abort_unless(Auth::check(), 401, '请先登录');

        $allowed = array_merge([], ...array_map(fn (string $r) => explode(',', $r), $roles));

        abort_unless(in_array($request->user()->role->value, $allowed, true), 403, '无权限访问');

        return $next($request);
    }
}
