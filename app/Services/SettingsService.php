<?php

namespace App\Services;

use App\Support\Tenant;

/**
 * 租户设置两层 token 解析器：`tenants.settings[key]` 覆盖 `config/site.defaults.key`。
 *
 * 统一读取定价/营销/分销/会员/合同等租户可配项；单租户下内部取 `Tenant::current()`，
 * 调用方不传租户参数。
 */
class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = Tenant::current()->settings ?? [];

        return $settings[$key] ?? config("site.defaults.{$key}", $default);
    }

    public function pricing(): array
    {
        return $this->get('pricing', []);
    }

    public function promotion(): array
    {
        return $this->get('promotion', []);
    }

    public function commission(): array
    {
        return $this->get('commission', []);
    }

    public function member(): array
    {
        return $this->get('member', []);
    }

    public function contract(): array
    {
        return $this->get('contract', []);
    }

    public function agreements(): array
    {
        return $this->get('agreements', []);
    }

    /** 页脚三层（版权主体 / 备案号 / 联系方式），空值回落 config 默认。 */
    public function footer(): array
    {
        return [
            'copyright' => (string) $this->get('footer_copyright', ''),
            'icp' => (string) $this->get('icp_number', '') ?: null,
            'contact' => (string) $this->get('contact', '') ?: null,
        ];
    }
}
