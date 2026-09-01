<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * 租户设置（tenant_admin）：品牌色 / SEO 分享文案 / 备案 / 联系方式 → tenants.settings。
 */
class SettingsController extends Controller
{
    public function edit(Tenant $tenant, Request $request)
    {
        return view('admin.settings.form', ['tenant' => $tenant]);
    }

    public function update(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            'brand_primary' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'brand_accent' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:200'],
            'seo_image' => ['nullable', 'string', 'max:500'],
            'footer_copyright' => ['nullable', 'string', 'max:120'],
            'icp_number' => ['nullable', 'string', 'max:60'],
            'contact' => ['nullable', 'string', 'max:200'],
        ]);

        $settings = $tenant->settings ?? [];
        $settings['brand'] = [
            'primary' => $data['brand_primary'] ?: ($settings['brand']['primary'] ?? config('site.defaults.brand.primary')),
            'accent' => $data['brand_accent'] ?: ($settings['brand']['accent'] ?? config('site.defaults.brand.accent')),
        ];
        $settings['seo'] = array_merge($settings['seo'] ?? [], array_filter([
            'title' => $data['seo_title'] ?? null,
            'description' => $data['seo_description'] ?? null,
            'image' => $data['seo_image'] ?? null,
        ], fn ($v) => $v !== null));
        $settings['footer_copyright'] = $data['footer_copyright'] ?? null;
        $settings['icp_number'] = $data['icp_number'] ?? null;
        $settings['contact'] = $data['contact'] ?? null;

        $tenant->update(['settings' => $settings]);

        return redirect()->route('tenant.admin.settings.edit', ['tenant' => $tenant->slug])
            ->with('ok', '设置已保存');
    }
}
