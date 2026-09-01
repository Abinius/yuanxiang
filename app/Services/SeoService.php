<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * SEO / 分享元数据：平台默认 + 租户覆盖 + 页面级覆盖（三层）。
 * 合规：cert_status=not_started，文案禁「有机产品/有机认证」，用「生态种植/有机肥（NXLB）投入品」。
 */
class SeoService
{
    public static function fromTenant(Tenant $tenant, array $overrides = []): array
    {
        return array_merge(config('site.defaults'), $tenant->settings['seo'] ?? [], $overrides);
    }
}
