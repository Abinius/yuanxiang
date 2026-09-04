<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * 租户设置两层 token 解析器：`tenants.settings[key]` 覆盖 `config/site.defaults.key`。
 *
 * 统一读取定价/营销/分销/会员/合同等租户可配项；M2 起 PlotController 等消费方
 * 经此读取，避免散落 `config(...)` 硬编码。
 */
class SettingsService
{
    /** 取一个顶层键的整块配置（数组）。 */
    public function get(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        $settings = $tenant->settings ?? [];

        return $settings[$key] ?? config("site.defaults.{$key}", $default);
    }

    public function pricing(Tenant $tenant): array
    {
        return $this->get($tenant, 'pricing', []);
    }

    public function promotion(Tenant $tenant): array
    {
        return $this->get($tenant, 'promotion', []);
    }

    public function commission(Tenant $tenant): array
    {
        return $this->get($tenant, 'commission', []);
    }

    public function member(Tenant $tenant): array
    {
        return $this->get($tenant, 'member', []);
    }

    public function contract(Tenant $tenant): array
    {
        return $this->get($tenant, 'contract', []);
    }
}
