<?php

namespace Database\Seeders;

use App\Enums\FarmLogType;
use App\Models\DetectionReport;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 2.5 溯源演示：mock NXLB 批次 + 溯源节点 + 检测报告 + 采收。
 * P4（NXLB 真实资料，W4）到位后由家人端录入或替换本 seeder 即上线，不改码。
 * 不进 DatabaseSeeder，测试自建数据。
 * 合规：cert_status=not_started，仅写「有机肥（NXLB）投入品」，无「有机产品/有机认证」。
 */
class TraceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'guangcai')->firstOrFail();
        $farm = Farm::where('tenant_id', $tenant->id)->firstOrFail();
        $plot = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')->orderBy('id')->firstOrFail();

        $a = FertilizerBatch::create([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'batch_no' => 'NXLB-2026-001',
            'produced_at' => '2026-03-10',
            'nxlb_ref' => 'NXLB-REF-2026-001',
            'ingredients' => '有机质≥45% · 腐殖酸 · 枯草芽孢杆菌',
            'test_report_url' => 'https://example.org/nxlb/2026-001.pdf', // P4 真资料替换
        ]);

        $b = FertilizerBatch::create([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'batch_no' => 'NXLB-2026-002',
            'produced_at' => '2026-06-20',
            'nxlb_ref' => 'NXLB-REF-2026-002',
            'ingredients' => '有机质≥45% · 腐殖酸 · 枯草芽孢杆菌',
            'test_report_url' => null,
        ]);

        $trace = fn (FarmLogType $type, string $title, string $content, string $at, ?FertilizerBatch $batch = null) =>
            FarmLog::create([
                'tenant_id' => $tenant->id,
                'farm_id' => $farm->id,
                'plot_id' => $plot->id,
                'type' => $type->value,
                'title' => $title,
                'content' => $content,
                'occurred_at' => $at,
                'is_public' => true,
                'source' => 'family',
                'is_trace_node' => true,
                'fertilizer_batch_id' => $batch?->id,
            ]);

        $trace(FarmLogType::Fertilize, '有机肥基施', 'NXLB 有机肥开沟基施，每株约 2kg。', '2026-03-12 08:00:00', $a);
        $trace(FarmLogType::Fertilize, '夏果追肥', 'NXLB 有机肥二次追施。', '2026-05-08 08:00:00', $b);
        $trace(FarmLogType::Inspect, '农残快检', '基地采样农残快检，结果合格。', '2026-07-15 10:00:00');
        $trace(FarmLogType::Harvest, '夏果首采', '夏果成熟，首采晾晒入帘。', '2026-08-02 09:00:00'); // 与 harvests 同日 → 叙事并入

        DetectionReport::create([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'plot_id' => $plot->id,
            'report_no' => 'JC-2026-001',
            'type' => 'pesticide',
            'batch_ref' => 'NXLB-2026-001',
            'org' => '宁夏农产品检测中心',
            'report_url' => 'https://example.org/jc/2026-001.pdf',
            'result_summary' => ['毒死蜱' => '未检出', '高效氯氟氰菊酯' => '未检出'],
            'qualified' => true,
            'issued_at' => '2026-07-16',
        ]);

        Harvest::create([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'plot_id' => $plot->id,
            'season_year' => 2026,
            'harvested_at' => '2026-08-02',
            'dry_weight_kg' => 12.50,
            'quality_grade' => '一级',
            'notes' => '夏果首采',
        ]);
    }
}
