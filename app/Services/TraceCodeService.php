<?php

namespace App\Services;

use App\Models\Harvest;
use App\Models\TraceCode;
use App\Support\Code;

/**
 * 溯源码生成：按采收（harvest）批量生成 N 条「每箱一码」。
 * 格式 TC{YYYYMMDD}-{8位随机大写}；DB code 唯一索引 + 生成循环查重双保险。
 * adoption_id 本期留空（与 deliveries/gift_boxes 关联留 Sprint 3）。
 */
class TraceCodeService
{
    /**
     * @param  int  $count       生成数量（每箱一码）
     * @param  int|null  $adoptionId  绑定到具体认养单；null 则留空（独立生码场景）
     * @return TraceCode[]
     */
    public function generate(Harvest $harvest, int $count = 1, ?int $adoptionId = null): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = TraceCode::create([
                'tenant_id' => $harvest->tenant_id,
                'code' => $this->uniqueCode(),
                'adoption_id' => $adoptionId,
                'harvest_id' => $harvest->id,
                'plot_id' => $harvest->plot_id,
                'scanned_count' => 0,
                'chain_hash' => null,
            ]);
        }

        return $codes;
    }

    private function uniqueCode(): string
    {
        return Code::dated('TC', fn ($c) => TraceCode::where('code', $c)->exists(), 8, '溯源码');
    }
}
