<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * 行级多租户：有租户上下文时全局过滤 tenant_id，并自动注入新建记录。
 * 用于所有带 tenant_id 的业务表模型（User 除外——账号认证不自动过滤）。
 */
trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (TenantContext::has()) {
                /** @var Model $model */
                $model = $builder->getModel();
                $builder->where($model->qualifyColumn('tenant_id'), TenantContext::id());
            }
        });

        static::creating(function (Model $model) {
            $tenantId = $model->getAttribute('tenant_id');

            if (TenantContext::has()) {
                if (! $tenantId) {
                    $model->setAttribute('tenant_id', TenantContext::id());
                }

                return;
            }

            // 无租户上下文时，必须显式携带 tenant_id（平台侧/种子数据），否则拦截写入
            if ($tenantId === null) {
                throw new RuntimeException('缺少租户上下文或 tenant_id，拒绝写入：'.class_basename($model));
            }
        });
    }
}
