<?php

namespace App\Tenancy;

/**
 * Job 内租户上下文作用域：队列 worker 是长生命周期进程，TenantContext 静态变量
 * 会跨 Job 残留。本 trait 提供 withTenantContext() 包裹 handle() 主体,
 * 保证每个 Job 独立设/清租户上下文,避免串租户查询或 TenantScoped 抛 RuntimeException。
 *
 * 用法:
 *   class XxxJob implements ShouldQueue {
 *       use Queueable, HandlesTenantContext;
 *       public function handle(...): void {
 *           $model = Model::find($id);
 *           $this->withTenantContext($model->tenant_id, function () use ($model, ...) {
 *               // 内部所有 TenantScoped 查询自动按该租户过滤
 *           });
 *       }
 *   }
 */
trait HandlesTenantContext
{
    protected function withTenantContext(int $tenantId, callable $fn): void
    {
        $prev = TenantContext::id();
        TenantContext::set($tenantId);
        try {
            $fn();
        } finally {
            TenantContext::set($prev);
        }
    }
}
