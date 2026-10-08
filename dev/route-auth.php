<?php

/**
 * 路由鉴权口径的唯一来源：由 Route::gatherMiddleware() 推导「公开 / 登录 / 登录+角色」。
 *
 * 注意 gatherMiddleware() 返回的是普通列表（形如 ['web', 'auth', 'role:tenant_admin']），
 * 不能用 isset($list['auth']) 判断——那样永远为假，会把所有路由都当成公开。
 */
function routeAuthLabel(array $middleware): string
{
    if (! in_array('auth', $middleware, true)) {
        return '公开';
    }

    foreach ($middleware as $m) {
        if (str_starts_with((string) $m, 'role:')) {
            return '登录 + role：'.substr((string) $m, 5);
        }
    }

    return '登录';
}

/** 是否挂了 auth（冒烟脚本用它区分「受保护路由 403 属预期」）。 */
function routeIsGuarded(array $middleware): bool
{
    return in_array('auth', $middleware, true);
}
