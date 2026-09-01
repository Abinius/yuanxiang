<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 租户中间件：解析 {tenant:slug} → 注入 TenantContext。
 * 闭包路由无类型提示时不触发隐式绑定，route('tenant') 可能是字符串，故统一按 slug 解析。
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenantParam = $request->route('tenant');
        $tenant = $tenantParam instanceof Tenant
            ? $tenantParam
            : Tenant::where('slug', (string) $tenantParam)->first();

        if (! $tenant) {
            throw new NotFoundHttpException('云村庄不存在');
        }

        if (! in_array($tenant->status->value, ['active', 'trial'], true)) {
            abort(403, '该云村庄暂不可用');
        }

        TenantContext::set($tenant->id);
        $request->attributes->set('tenant', $tenant);

        try {
            return $next($request);
        } finally {
            TenantContext::reset();
        }
    }
}
