<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 单租户下原跨租户成员校验已无意义：退化为透传，保留别名以免大改路由。
 * 可在后续清理中移除别名与路由引用。
 */
class TenantMemberMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
