<?php

namespace App\Services;

use App\Models\Harvest;
use App\Models\TraceCode;
use Illuminate\Support\Str;

/**
 * 溯源码生成：按采收（harvest）批量生成 N 条「每箱一码」。
 * 格式 TC{YYYYMMDD}-{8位随机大写}；DB code 唯一索引 + 生成循环查重双保险。
 * adoption_id 本期留空（与 deliveries/gift_boxes 关联留 Sprint 3）。
 */
class TraceCodeService
{
    private const MAX_COLLISION_RETRY = 5;

    /** @return TraceCode[] */
    public function generate(Harvest $harvest, int $count = 1): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = TraceCode::create([
                'tenant_id' => $harvest->tenant_id,
                'code' => $this->uniqueCode(),
                'adoption_id' => null,
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
        for ($attempt = 0; $attempt < self::MAX_COLLISION_RETRY; $attempt++) {
            $code = 'TC'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
            if (! TraceCode::where('code', $code)->exists()) {
                return $code;
            }
        }

        // 8 位大写字母+数字空间极大，碰撞几乎不可能；兜底抛错防静默重复
        throw new \RuntimeException('溯源码生成碰撞，请重试');
    }
}
