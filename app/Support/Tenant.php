<?php

namespace App\Support;

use App\Models\Tenant as TenantModel;
use RuntimeException;

/**
 * 单租户辅助：返回唯一租户行。
 *
 * 替代原多租户下的 `Tenant $tenant` 路由参数注入——控制器/服务不再接收
 * `$tenant` 实参，需要租户配置（settings 等）时调 `Tenant::current()`。
 *
 * 不做容器缓存：settings 在同进程内可能被改动（测试/队列/后台），缓存会读到旧值。
 * 单租户一行查询开销可忽略；如需省查询，调用方自行缓存取到的实例。
 */
class Tenant
{
    public static function current(): TenantModel
    {
        $tenant = TenantModel::query()->first();

        if (!$tenant) {
            throw new RuntimeException('无租户数据，请先初始化 tenants 表');
        }

        return $tenant;
    }
}
