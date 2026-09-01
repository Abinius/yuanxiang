<?php

namespace App\Services;

use App\Enums\AdjustmentType;
use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\AdoptionAdjustment;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;

/**
 * 3.2 保底规则引擎（DB 设计 §4.1）：采收结算时按 delivery_rule 生成补退记录。
 *
 * MVP 只处理欠收（默认折算退钱 ¥150/kg，无超产库存池）；平年/丰收不落库；
 * 单株 pool_mode 不支持（家人端采收只录分地）。
 * apply：pending→applied，refund_prorated 走部分退款（mock 分支）。
 */
class AdjustmentService
{
    public function __construct(private readonly WeChatPayService $pay)
    {
    }

    /** 生成某年度补退记录（幂等）。@return AdoptionAdjustment[] */
    public function runForSeason(Tenant $tenant, int $seasonYear): array
    {
        $adoptions = Adoption::query()
            ->where('tenant_id', $tenant->id)
            ->where('season_year', $seasonYear)
            ->where('adoptable_type', Plot::class)
            ->where('status', AdoptionStatus::Active)
            ->with('plan')
            ->get();

        $created = [];
        foreach ($adoptions as $adoption) {
            $rule = $adoption->plan?->delivery_rule ?? [];
            $guarantee = (float) data_get($rule, 'guarantee_kg', 0);
            $compensateTo = (float) data_get($rule, 'shortfall.compensate_to_kg', $guarantee);
            $severeThreshold = (float) data_get($rule, 'shortfall.severe_threshold_kg', 0);
            $refundPriceKg = (float) data_get($rule, 'shortfall.refund_price_kg', 0);

            $harvestTotal = Harvest::query()
                ->where('plot_id', $adoption->adoptable_id)
                ->where('season_year', $seasonYear)
                ->sum('dry_weight_kg');

            if ($harvestTotal >= $guarantee) {
                continue; // 平年/丰收，MVP 不记录
            }

            $gap = max(0, $compensateTo - $harvestTotal);
            $amount = round($gap * $refundPriceKg, 2);
            $severe = $harvestTotal < $severeThreshold;

            $exists = AdoptionAdjustment::query()
                ->where('adoption_id', $adoption->id)
                ->where('season_year', $seasonYear)
                ->exists();
            if ($exists) {
                continue;
            }

            $created[] = AdoptionAdjustment::create([
                'tenant_id' => $tenant->id,
                'adoption_id' => $adoption->id,
                'season_year' => $seasonYear,
                'type' => AdjustmentType::RefundProrated->value,
                'amount' => $amount,
                'reason' => $severe ? '严重欠收，差额退费 + 下季优先认养权' : '欠收，按保底折算退费',
                'status' => 'pending',
            ]);
        }

        return $created;
    }

    public function apply(AdoptionAdjustment $adjustment): void
    {
        abort_unless($adjustment->status === 'pending', 422, '仅待处理可应用');

        // 注：adoption_adjustments.type 无 enum cast，DB 值为字符串，须与 ->value 比较（此前比较枚举对象恒 false，退款分支从未执行）
        if ($adjustment->type === AdjustmentType::RefundProrated->value && (float) $adjustment->amount > 0) {
            // 确定性 out_refund_no（按补退记录 id）：重试/重放时微信侧按 out_refund_no 幂等去重，防二次真退费
            $this->pay->requestRefund(
                $adjustment->adoption,
                $adjustment->reason ?? '缺产折算退费',
                (float) $adjustment->amount,
                'RF-'.$adjustment->id,
            );
        }

        $adjustment->forceFill(['status' => 'applied'])->save();
    }
}
