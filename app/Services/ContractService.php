<?php

namespace App\Services;

use App\Models\Adoption;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * M3 认养合同：签约时生成合同实体（条款快照 + 编号 + IP 留痕）。
 *
 * - 条款按当时 template_version 快照写入 clauses，后续模板改动不影响已签合同。
 * - 合同编号：{年}-{租户slug}-{序号4位}，租户内唯一。
 * - v1 用 HTML 可打印视图（浏览器另存 PDF）；pdf_path 字段预留给后续 dompdf。
 */
class ContractService
{
    /** 当前合同条款版本（读 settings.contract，回落 v1）。 */
    public function templateVersion(Tenant $tenant): string
    {
        return app(SettingsService::class)->contract($tenant)['template_version'] ?? 'v1';
    }

    /** 生成下一个合同编号（租户内年度递增）。 */
    public function nextContractNo(Tenant $tenant): string
    {
        $year = now()->format('Y');
        $prefix = "{$year}-{$tenant->slug}-";

        $seq = Contract::query()
            ->where('tenant_id', $tenant->id)
            ->where('contract_no', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** 按认养单生成条款快照数组。 */
    public function buildClauses(Adoption $adoption): array
    {
        $plot = $adoption->adoptable;
        $plan = $adoption->plan;
        $guarantee = $plot instanceof \App\Models\Plot ? $plot->guaranteeKg() : null;
        $typeLabel = $plot instanceof \App\Models\Plot
            ? ($plot->type->value === 'plant' ? '单株' : ($plot->type->value === 'group' ? '拼团田' : '分地'))
            : '认养单元';
        $muArea = $plot instanceof \App\Models\Plot ? $plot->mu_area : null;
        $paidAt = $adoption->payments()->where('status', 'paid')->value('paid_at');

        $rows = [
            ['title' => '认养标的', 'body' => sprintf(
                '甲方认养 %s（%s，%s），位于宁夏红寺堡光彩村基地。',
                $plot?->code ?? '认养单元',
                $typeLabel,
                $muArea ? $muArea.' 亩' : '按单元',
            )],
            ['title' => '认养期限', 'body' => sprintf(
                '%s 至 %s（一年）。',
                $adoption->start_date?->format('Y-m-d') ?? '—',
                $adoption->end_date?->format('Y-m-d') ?? '—',
            )],
            ['title' => '认养费用', 'body' => sprintf(
                '年费 %s 元，已于 %s 支付完毕。',
                number_format((float) $adoption->annual_fee),
                $paidAt ? \Illuminate\Support\Carbon::parse($paidAt)->format('Y-m-d') : '—',
            )],
            ['title' => '保底产量与丰欠共担', 'body' => $guarantee !== null
                ? sprintf('保底 %s kg 干果/年；不足部分由丰欠共担池补齐；超额归甲方。', rtrim(rtrim(number_format($guarantee, 2), '0'), '.'))
                : '按采收实际产出交付，丰欠共担。'],
            ['title' => '配送与礼盒', 'body' => '三节（春节/端午/中秋）定制礼盒配额 + 采收期干果配送，详见平台配送记录。'],
            ['title' => '溯源与透明', 'body' => '种植全程可溯源：有机肥(NXLB)批次、农事记录、采收报告对甲方公开。'],
            ['title' => '命名与挂牌', 'body' => $adoption->named_label
                ? sprintf('甲方可为地块命名「%s」，挂牌展示。', $adoption->named_label)
                : '甲方可为地块命名并挂牌展示。'],
            ['title' => '退款规则', 'body' => '下单 72 小时内未支付可取消；生效后退款依平台规则办理。'],
            ['title' => '争议与解释', 'body' => '本合同条款以平台公示版本为准；争议协商解决。'],
        ];

        return $rows;
    }

    /** 签约时生成合同记录（幂等：同认养已有合同则返回旧的）。 */
    public function createFor(Adoption $adoption, ?string $signedIp = null): Contract
    {
        return DB::transaction(function () use ($adoption, $signedIp) {
            $existing = Contract::query()->where('adoption_id', $adoption->id)->first();
            if ($existing) {
                return $existing;
            }

            $tenant = $adoption->tenant;

            return Contract::create([
                'tenant_id' => $tenant->id,
                'adoption_id' => $adoption->id,
                'contract_no' => $this->nextContractNo($tenant),
                'template_version' => $this->templateVersion($tenant),
                'clauses' => $this->buildClauses($adoption),
                'signed_at' => $adoption->agreement_signed_at ?? now(),
                'signed_ip' => $signedIp,
                'status' => 'signed',
            ]);
        });
    }
}
