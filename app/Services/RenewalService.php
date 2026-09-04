<?php

namespace App\Services;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Tenancy\TenantContext;

/**
 * F9：续费到期调度 + auto_renew 消费。
 *
 * - 提醒窗口：到期前 30/7/1 天对 active 认养推"即将到期"（模板消息 mock 通道）。
 * - auto_renew=true 且临期（≤7 天）→ 自动建下一季待支付单（真扣款待商户号，
 *   降级为待支付单，云乡民确认支付即续上；不重复建单）。
 */
class RenewalService
{
    /** 30/7/1 天提醒窗口（天 → 模板 key）。 */
    public const WINDOWS = [
        30 => 'renewal_notice',
        7  => 'renewal_notice',
        1  => 'renewal_notice',
    ];

    public function __construct(
        private readonly AdoptionService $adoptions,
        private readonly PromotionService $promotions,
        private readonly WechatTemplateService $templates,
    ) {
    }

    /** 查询到期窗口内的 active 认养。 */
    public function expiringInDays(int $days, ?int $tenantId = null): \Illuminate\Support\Collection
    {
        $from = now()->startOfDay()->addDays($days);
        $to = $from->copy()->endOfDay();

        return Adoption::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', AdoptionStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$from, $to])
            ->with('user')
            ->get();
    }

    /** 到期窗口内推送（mock 落库；按租户作用域，避免跨租户）。 */
    public function sendReminders(int $days): int
    {
        $sent = 0;
        foreach ($this->expiringInDays($days) as $adoption) {
            $user = $adoption->user;
            if (! $user || ! $user->openid) {
                continue;
            }

            $prev = TenantContext::id();
            TenantContext::set($adoption->tenant_id);
            try {
                $this->templates->send($user, 'renewal_notice', [
                    'url' => route('tenant.home', ['tenant' => $adoption->tenant->slug]),
                    'data' => [
                        'thing1' => ['value' => $adoption->adoptable?->code ?? '你的田'],
                        'thing2' => ['value' => '认养即将到期，续费继续看它长大（剩 '.$days.' 天）'],
                    ],
                ]);
            } finally {
                TenantContext::set($prev);
            }
            $sent++;
        }

        return $sent;
    }

    /**
     * auto_renew 消费：临期（≤7 天内到期）且已开启续费意向的 active 单 → 自动建下一季待支付单。
     * 幂等：已有 renewed_from_id=本单 的下一季单则跳过。
     */
    public function autoRenewExpiring(): int
    {
        $created = 0;

        $from = now()->startOfDay();
        $to = $from->copy()->addDays(7)->endOfDay();

        $expiring = Adoption::query()
            ->where('status', AdoptionStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$from, $to])
            ->with('user')
            ->get();

        foreach ($expiring as $adoption) {
            // 是否已建下一季单（含进行中的待支付）
            $already = Adoption::query()
                ->where('renewed_from_id', $adoption->id)
                ->whereIn('status', [
                    AdoptionStatus::PendingPayment->value,
                    AdoptionStatus::PendingAgreement->value,
                    AdoptionStatus::Active->value,
                ])
                ->exists();
            if ($already) {
                continue;
            }

            if (! $adoption->auto_renew) {
                continue;
            }

            $user = $adoption->user;
            if (! $user) {
                continue;
            }

            // 复用续费逻辑：自动用 renewal 券（若有）
            $coupon = $user->coupons()
                ->where('status', 'unused')
                ->whereHas('promotion', fn ($q) => $q->where('type', 'renewal')->where('status', 'active'))
                ->first();

            $prev = TenantContext::id();
            TenantContext::set($adoption->tenant_id);
            try {
                $this->promotions->renew($user, $adoption, $coupon);
            } finally {
                TenantContext::set($prev);
            }
            $created++;
        }

        return $created;
    }
}