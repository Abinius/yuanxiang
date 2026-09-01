<?php

namespace Database\Seeders;

use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Tenant;
use App\Services\TraceCodeService;
use Illuminate\Database\Seeder;

/**
 * 2.6 溯源码演示：为 TraceSeeder 造的首批 NXLB 批次所关联 plot 的首条采收生成 3 条箱码。
 * 先跑 TraceSeeder 再跑本 seeder；不进 DatabaseSeeder，测试自建数据。
 */
class TraceCodeSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();

        $batch = FertilizerBatch::where('tenant_id', $tenant->id)->orderBy('id')->firstOrFail();
        $plot = FarmLog::where('tenant_id', $tenant->id)
            ->where('fertilizer_batch_id', $batch->id)
            ->firstOrFail()
            ->plot;
        $harvest = $plot->harvests()->orderBy('id')->firstOrFail();

        $generated = app(TraceCodeService::class)->generate($harvest, 3);

        $this->command?->info('TraceCodeSeeder done: '.count($generated).' 条箱码');
    }
}
