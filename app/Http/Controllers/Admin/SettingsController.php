<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\SettingsService;
use Illuminate\Http\Request;

/**
 * 租户设置（tenant_admin）：品牌/SEO/页脚 + 定价/营销/分销佣金/会员阶梯/合同（M2）。
 *
 * 两层 token：`config/site.defaults` 默认 + `tenants.settings` 覆盖；
 * 写入后由 SettingsService 统一读取，消费方不再散落 config(...) 硬编码。
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit(Tenant $tenant, Request $request)
    {
        return view('admin.settings.form', [
            'tenant' => $tenant,
            'pricing' => $this->settings->pricing($tenant),
            'promotion' => $this->settings->promotion($tenant),
            'commission' => $this->settings->commission($tenant),
            'member' => $this->settings->member($tenant),
            'contract' => $this->settings->contract($tenant),
        ]);
    }

    public function update(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            // 品牌 / SEO / 页脚（原有）
            'brand_primary' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'brand_accent' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:200'],
            'seo_image' => ['nullable', 'string', 'max:500'],
            'footer_copyright' => ['nullable', 'string', 'max:120'],
            'icp_number' => ['nullable', 'string', 'max:60'],
            'contact' => ['nullable', 'string', 'max:200'],
            // 定价
            'fendi_yearly' => ['nullable', 'integer', 'min:0'],
            'zhu_yearly' => ['nullable', 'integer', 'min:0'],
            'trial_pack_min' => ['nullable', 'integer', 'min:0'],
            'trial_pack_max' => ['nullable', 'integer', 'min:0'],
            'guarantee_fendi' => ['nullable', 'numeric', 'min:0'],
            'guarantee_zhu' => ['nullable', 'numeric', 'min:0'],
            // 营销
            'referral_new' => ['nullable', 'integer', 'min:0'],
            'referral_referrer' => ['nullable', 'integer', 'min:0'],
            'new_customer' => ['nullable', 'integer', 'min:0'],
            'renewal' => ['nullable', 'integer', 'min:0'],
            // 分销佣金
            'rate_red' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rate_expert' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rate_partner' => ['nullable', 'integer', 'min:0', 'max:10'],
            'cooldown_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            // 会员阶梯
            'tier_red' => ['nullable', 'integer', 'min:0'],
            'tier_expert' => ['nullable', 'integer', 'min:0'],
            'tier_partner' => ['nullable', 'integer', 'min:0'],
            // 合同
            'contract_template_version' => ['nullable', 'string', 'max:20'],
        ]);

        // 补全所有键（PUT 可能只带部分分区，未发送的键置 null，避免 Undefined array key）
        $data = array_merge(array_fill_keys([
            'brand_primary', 'brand_accent', 'seo_title', 'seo_description', 'seo_image',
            'footer_copyright', 'icp_number', 'contact',
            'fendi_yearly', 'zhu_yearly', 'trial_pack_min', 'trial_pack_max', 'guarantee_fendi', 'guarantee_zhu',
            'referral_new', 'referral_referrer', 'new_customer', 'renewal',
            'rate_red', 'rate_expert', 'rate_partner', 'cooldown_days',
            'tier_red', 'tier_expert', 'tier_partner',
            'contract_template_version',
        ], null), $data);

        $settings = $tenant->settings ?? [];

        // 品牌 / SEO / 页脚
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

        // 定价（M2）
        $prevPricing = $settings['pricing'] ?? config('site.defaults.pricing');
        $settings['pricing'] = [
            'fendi_yearly' => $this->filled($data['fendi_yearly'], $prevPricing['fendi_yearly'] ?? null, 'int'),
            'zhu_yearly' => $this->filled($data['zhu_yearly'], $prevPricing['zhu_yearly'] ?? null, 'int'),
            'trial_pack' => [
                'min' => $this->filled($data['trial_pack_min'], $prevPricing['trial_pack']['min'] ?? null, 'int'),
                'max' => $this->filled($data['trial_pack_max'], $prevPricing['trial_pack']['max'] ?? null, 'int'),
            ],
            'guarantee_kg' => [
                'fendi' => $this->filled($data['guarantee_fendi'], $prevPricing['guarantee_kg']['fendi'] ?? null, 'num'),
                'zhu' => $this->filled($data['guarantee_zhu'], $prevPricing['guarantee_kg']['zhu'] ?? null, 'num'),
            ],
        ];

        // 营销
        $prevPromo = $settings['promotion'] ?? config('site.defaults.promotion');
        $settings['promotion'] = [
            'referral' => [
                'new' => $this->filled($data['referral_new'], $prevPromo['referral']['new'] ?? null, 'int'),
                'referrer' => $this->filled($data['referral_referrer'], $prevPromo['referral']['referrer'] ?? null, 'int'),
            ],
            'new_customer' => $this->filled($data['new_customer'], $prevPromo['new_customer'] ?? null, 'int'),
            'renewal' => $this->filled($data['renewal'], $prevPromo['renewal'] ?? null, 'int'),
        ];

        // 分销佣金（≤10% 合规校验已在上限）
        $prevCom = $settings['commission'] ?? config('site.defaults.commission');
        $settings['commission'] = [
            'rates' => [
                'red' => $this->filled($data['rate_red'], $prevCom['rates']['red'] ?? null, 'int'),
                'expert' => $this->filled($data['rate_expert'], $prevCom['rates']['expert'] ?? null, 'int'),
                'partner' => $this->filled($data['rate_partner'], $prevCom['rates']['partner'] ?? null, 'int'),
            ],
            'cooldown_days' => $this->filled($data['cooldown_days'], $prevCom['cooldown_days'] ?? null, 'int'),
        ];

        // 会员阶梯
        $prevMem = $settings['member'] ?? config('site.defaults.member');
        $settings['member'] = [
            'tiers' => [
                'red' => $this->filled($data['tier_red'], $prevMem['tiers']['red'] ?? null, 'int'),
                'expert' => $this->filled($data['tier_expert'], $prevMem['tiers']['expert'] ?? null, 'int'),
                'partner' => $this->filled($data['tier_partner'], $prevMem['tiers']['partner'] ?? null, 'int'),
            ],
        ];

        // 合同
        $prevCon = $settings['contract'] ?? config('site.defaults.contract');
        $settings['contract'] = [
            'template_version' => $this->filled($data['contract_template_version'], $prevCon['template_version'] ?? 'v1', 'str'),
        ];

        $tenant->update(['settings' => $settings]);

        return redirect()->route('tenant.admin.settings.edit', ['tenant' => $tenant->slug])
            ->with('ok', '设置已保存');
    }

    /** 空值回落到前值；按类型强转。 */
    private function filled(mixed $value, mixed $fallback, string $type): mixed
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return match ($type) {
            'int' => (int) $value,
            'num' => (float) $value,
            'str' => (string) $value,
            default => $value,
        };
    }
}
