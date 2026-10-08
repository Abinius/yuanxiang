<?php

namespace App\Models\Concerns;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * 单租户下自动注入 tenant_id：新建记录未显式给 tenant_id 时，
 * 取 Tenant::current()->id 填充。不再做全局作用域过滤（单租户无意义）。
 */
trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::creating(function (Model $model) {
            if (! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', Tenant::current()->id);
            }
        });
    }
}
