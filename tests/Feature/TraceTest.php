<?php

namespace Tests\Feature;

use App\Enums\FarmLogType;
use App\Models\DetectionReport;
use App\Models\Farm;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2.5 溯源时间线：公开页（无 auth）、只含 is_trace_node 节点、施肥带 NXLB 批次卡、
 * harvests 权威采收 + 同日 farm_log 叙事并入、检测报告、租户隔离 404、认养页入口。
 */
class TraceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
    }

    private function traceLog(Plot $plot, FarmLogType $type, string $title, string $at, bool $trace = true, ?FertilizerBatch $batch = null): FarmLog
    {
        return FarmLog::create([
            'tenant_id' => $plot->tenant_id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $plot->id,
            'type' => $type->value,
            'title' => $title,
            'content' => '节点内容',
            'occurred_at' => $at,
            'is_public' => true,
            'is_trace_node' => $trace,
            'source' => 'family',
            'fertilizer_batch_id' => $batch?->id,
        ]);
    }

    public function test_guest_can_view_public_trace_and_daily_weed_excluded(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();

        $this->traceLog($plot, FarmLogType::Fertilize, '有机肥基施', '2026-04-05 08:00:00');
        $this->traceLog($plot, FarmLogType::Daily, '日常巡田', '2026-05-01 08:00:00', false);
        $this->traceLog($plot, FarmLogType::Weed, '除草', '2026-05-02 08:00:00', false);

        // guest 未登录直接可看
        $this->get("/t/{$t->slug}/trace/{$plot->id}")
            ->assertOk()
            ->assertSee('有机肥基施')
            ->assertDontSee('日常巡田')
            ->assertDontSee('除草');
    }

    public function test_fertilize_shows_batch_and_timeline_is_ascending(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();

        $batch = FertilizerBatch::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'batch_no' => 'NXLB-2026-001',
            'produced_at' => '2026-03-10',
            'ingredients' => '有机质≥45%',
        ]);
        $this->traceLog($plot, FarmLogType::Fertilize, '基施', '2026-03-12 08:00:00', true, $batch);
        $this->traceLog($plot, FarmLogType::Fertilize, '追肥', '2026-05-08 08:00:00');

        $this->get("/t/{$t->slug}/trace/{$plot->id}")
            ->assertOk()
            ->assertSee('NXLB-2026-001')
            ->assertSee('有机质≥45%')
            ->assertSeeInOrder(['基施', '追肥']);
    }

    public function test_cross_tenant_plot_is_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);
        $otherPlot = Plot::create([
            'tenant_id' => $other->id,
            'farm_id' => $this->farm()->id,
            'type' => 'plot',
            'code' => 'X-01',
            'mu_area' => 0.1,
            'price_yearly' => 5000,
        ]);

        $this->get("/t/{$t->slug}/trace/{$otherPlot->id}")
            ->assertNotFound();
    }

    public function test_harvest_merges_farm_log_note_and_shows_detection_report(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();

        Harvest::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $plot->id,
            'season_year' => 2026,
            'harvested_at' => '2026-08-02',
            'dry_weight_kg' => 12.50,
            'quality_grade' => '一级',
        ]);
        $this->traceLog($plot, FarmLogType::Harvest, '夏果首采', '2026-08-02 09:00:00');

        DetectionReport::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $plot->id,
            'report_no' => 'JC-2026-001',
            'type' => 'pesticide',
            'org' => '宁夏检测中心',
            'qualified' => true,
            'issued_at' => '2026-07-16',
            'result_summary' => ['毒死蜱' => '未检出'],
        ]);

        $this->get("/t/{$t->slug}/trace/{$plot->id}")
            ->assertOk()
            ->assertSee('2026 年度采收')
            ->assertSee('一级')
            ->assertSee('夏果首采')   // 同日 farm_log 叙事并入
            ->assertSee('JC-2026-001')
            ->assertSee('毒死蜱');
    }

    public function test_adopt_show_has_trace_link(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();

        $this->get("/t/{$t->slug}/adopt/{$plot->id}")
            ->assertOk()
            ->assertSee('查看溯源');
    }
}
