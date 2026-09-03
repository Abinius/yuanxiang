<?php

namespace App\Services;

use App\Enums\AdoptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlotStatus;
use App\Enums\PlotType;
use App\Models\Address;
use App\Models\Adoption;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 认养业务：下单（校验可认养/本季唯一）→ mock 支付（Sprint 2 换微信回调）
 *        → 签署协议 + 命名 → 生效（地块置已认养；拼团田末株认养后售罄）。
 */
class AdoptionService
{
    public function __construct(
        private readonly PromotionService $promotions,
        private readonly ContractService $contracts,
        private readonly CommissionService $commissions,
    ) {
    }

    public function createOrder(User $user, Plot $plot, array $addressData, array $options = []): Adoption
    {
        $seasonYear = $options['season_year'] ?? (int) now()->format('Y');
        $renewedFromId = $options['renewed_from_id'] ?? null;
        $coupon = $options['coupon'] ?? null;

        // 续费（renewed_from_id）复用原 plot 已被占用的情况，跳过占用检查
        if (! $renewedFromId) {
            abort_if($plot->status !== PlotStatus::Available, 422, '该田块当前不可认养');
            abort_if($plot->type === PlotType::Group, 422, '拼团田不可直接认养，请选择单株');
        }

        $plan = $plot->plan;
        abort_if(! $plan || $plan->status !== 'active', 422, '该档位方案不可用');

        $annualFee = (float) $plan->price_yearly;
        $amountOff = 0.0;
        if ($coupon) {
            abort_if($coupon->user_id !== $user->id, 422, '该券不属于当前用户');
            $amountOff = $this->promotions->discountFor($coupon, $plan, $seasonYear);
            $annualFee = max(0.0, $annualFee - $amountOff);
        }

        // 事务 + 唯一约束兜底:防并发下单超卖。
        // Address 写入放事务内,确保 adoption 失败时不回留孤儿地址。
        return DB::transaction(function () use ($user, $plot, $seasonYear, $renewedFromId, $plan, $annualFee, $coupon, $amountOff, $addressData) {
            if (! empty($addressData)) {
                Address::create(array_merge($addressData, ['user_id' => $user->id]));
            }
            $exists = Adoption::query()
                ->where('adoptable_type', Plot::class)
                ->where('adoptable_id', $plot->id)
                ->where('season_year', $seasonYear)
                ->whereIn('status', [
                    AdoptionStatus::PendingPayment->value,
                    AdoptionStatus::PendingAgreement->value,
                    AdoptionStatus::Active->value,
                ])
                ->exists();
            if ($exists) {
                abort(422, '该田块本季已被认养');
            }

            try {
                $adoption = Adoption::create([
                    'tenant_id' => $plot->tenant_id,
                    'adoption_no' => 'AD'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                    'user_id' => $user->id,
                    'adoptable_type' => Plot::class,
                    'adoptable_id' => $plot->id,
                    'plan_id' => $plan->id,
                    'farm_id' => $plot->farm_id,
                    'season_year' => $seasonYear,
                    'annual_fee' => $annualFee,
                    'renewed_from_id' => $renewedFromId,
                    'start_date' => now()->toDateString(),
                    'status' => AdoptionStatus::PendingPayment->value,
                ]);
            } catch (QueryException $e) {
                // 唯一约束冲突:另一请求已抢先下单。事务回滚,降级 422。
                abort(422, '该田块本季已被认养');
            }

            Payment::create([
                'tenant_id' => $plot->tenant_id,
                'payable_type' => Adoption::class,
                'payable_id' => $adoption->id,
                'amount' => $annualFee,
                'method' => 'manual',
                'status' => PaymentStatus::Pending->value,
            ]);

            if ($coupon && $amountOff > 0) {
                $this->promotions->recordUsage($coupon, $adoption, $amountOff);
            }

            return $adoption;
        });
    }

    /**
     * 幂等支付完成：pending 支付 → paid，认养 → 待签约。
     * 重复回调（已无 pending 支付）静默无副作用——2.1 验收口径。
     *
     * @param  array{transaction_id?: string, method?: string}  $meta
     */
    public function markPaid(Adoption $adoption, array $meta = []): void
    {
        $payment = $adoption->payments()
            ->where('status', PaymentStatus::Pending->value)
            ->latest('id')
            ->first();

        if (! $payment) {
            return;
        }

        DB::transaction(function () use ($payment, $adoption, $meta) {
            $payment->update([
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
                'transaction_id' => $meta['transaction_id'] ?? $payment->transaction_id,
                'method' => $meta['method'] ?? 'wechat',
            ]);

            $adoption->update(['status' => AdoptionStatus::PendingAgreement->value]);
        });
    }

    /**
     * 模拟支付成功（dev 用；真实链路走 WeChatPayService::parseNotify → markPaid）。
     */
    public function confirmMockPayment(Adoption $adoption): void
    {
        abort_unless($adoption->status === AdoptionStatus::PendingPayment, 422, '当前状态不支持支付');

        $this->markPaid($adoption, ['method' => 'manual']);
    }

    /**
     * 退款落库：已支付 → 已退款，认养 → 取消。幂等（无已支付或已退则静默）。
     */
    public function markRefunded(Adoption $adoption): void
    {
        $payment = $adoption->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->latest('id')
            ->first();

        if (! $payment) {
            return;
        }

        DB::transaction(function () use ($payment, $adoption) {
            $payment->update([
                'status' => PaymentStatus::Refunded->value,
                'refund_at' => now(),
            ]);

            $adoption->update(['status' => AdoptionStatus::Cancelled->value]);
            $this->commissions->freezeFor($adoption);
        });
    }

    /**
     * 弃付到期：订单创建 +72h 仍未支付 → 可续；超期则 markCancelled，释放田块。
     */
    public function expiresAt(Adoption $adoption): \Illuminate\Support\Carbon
    {
        return $adoption->created_at->copy()->addHours(72);
    }

    public function isExpired(Adoption $adoption): bool
    {
        return $adoption->status === AdoptionStatus::PendingPayment && now()->gt($this->expiresAt($adoption));
    }

    /** 续费 / 继续支付：仅限 pending_payment 且未过期。超期走重新下单。 */
    public function resumePayment(Adoption $adoption): bool
    {
        return $adoption->status === AdoptionStatus::PendingPayment && ! $this->isExpired($adoption);
    }

    /** 批量过期未付单（日调度调用）：pending_payment + 超过 72h → 取消并释放田块。 */
    public function expirePendingOrders(): int
    {
        $cutoff = now()->subHours(72);
        $expired = Adoption::query()
            ->where('status', AdoptionStatus::PendingPayment->value)
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($expired as $adoption) {
            $adoption->update(['status' => AdoptionStatus::Cancelled->value]);

            // 释放田块（本季节点已被占用的可能性：仅 pending_payment 占用，取消后回到可认养）
            if ($adoption->adoptable_type === Plot::class) {
                $plot = $adoption->adoptable;
                if ($plot->status === PlotStatus::Adopted) {
                    $plot->update(['status' => PlotStatus::Available->value]);
                }
            }

            $count++;
        }

        return $count;
    }

    /**
     * 签署认养协议 + 命名 → 生效。地块置已认养；拼团田末株认养后置售罄。
     */
    public function signAgreement(Adoption $adoption, string $namedLabel, ?string $signedIp = null): void
    {
        abort_unless($adoption->status === AdoptionStatus::PendingAgreement, 422, '当前状态不可签署协议');

        $adoption->update([
            'named_label' => $namedLabel,
            'agreement_signed_at' => now(),
            'end_date' => $adoption->start_date->copy()->addYear(),
            'status' => AdoptionStatus::Active->value,
        ]);

        $this->contracts->createFor($adoption, $signedIp);

        $this->commissions->credit($adoption);

        $this->occupyPlot($adoption);
    }

    private function occupyPlot(Adoption $adoption): void
    {
        if ($adoption->adoptable_type !== Plot::class) {
            return;
        }

        $plot = $adoption->adoptable;
        $plot->update(['status' => PlotStatus::Adopted->value]);

        // 株档：拼团田内已无可用株 → 拼团田置售罄
        if ($plot->parent_plot_id && $group = $plot->parent) {
            $group->refresh();
            if ($group->children()->where('status', PlotStatus::Available->value)->doesntExist()) {
                $group->update(['status' => PlotStatus::SoldOut->value]);
            }
        }
    }
}
