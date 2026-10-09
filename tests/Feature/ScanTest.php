<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\TraceCode;
use App\Models\User;
use App\Services\TraceCodeService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2.6 溯源码：公开扫码页（每箱一码）+ scanned_count 计数 + 租户隔离 404 +
 * 后台生成（按采收批量、唯一）/ 打印页 / 权限 403。
 */
class ScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function makeHarvest(Plot $plot, int $year = 2026): Harvest
    {
        return Harvest::create([
            'tenant_id' => $plot->tenant_id,
            'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id,
            'season_year' => $year,
            'harvested_at' => '2026-08-02',
            'dry_weight_kg' => 12.5,
            'quality_grade' => '一级',
        ]);
    }

    public function test_guest_scans_valid_code_sees_plot_and_harvest_and_increments(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();
        $harvest = $this->makeHarvest($plot);
        $code = TraceCode::create([
            'tenant_id' => $t->id,
            'code' => 'TC20260801-TEST0001',
            'harvest_id' => $harvest->id,
            'plot_id' => $plot->id,
            'scanned_count' => 0,
        ]);

        $this->get("/s/{$code->code}")
            ->assertOk()
            ->assertSee($code->code)
            ->assertSee($plot->code)
            ->assertSee('一级');

        $this->assertDatabaseHas('trace_codes', ['id' => $code->id, 'scanned_count' => 1]);
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("/s/TC-NOT-EXIST")
            ->assertNotFound();
    }

    public function test_admin_generates_codes_bound_to_harvest_and_redirects_to_print(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();
        $harvest = $this->makeHarvest($plot);
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/trace-codes", [
                'harvest_id' => $harvest->id,
                'count' => 3,
            ])
            ->assertRedirect();

        $codes = TraceCode::where('harvest_id', $harvest->id)->get();
        $this->assertCount(3, $codes);
        $this->assertSame(3, $codes->pluck('code')->unique()->count());
        foreach ($codes as $c) {
            $this->assertEquals($t->id, $c->tenant_id);
            $this->assertEquals($plot->id, $c->plot_id);
            $this->assertEquals($harvest->id, $c->harvest_id);
            $this->assertNull($c->adoption_id);
            $this->assertEquals(0, $c->scanned_count);
        }
    }

    public function test_generation_codes_are_unique_across_batches(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();
        $h1 = $this->makeHarvest($plot, 2026);
        $h2 = $this->makeHarvest($plot, 2025);

        $service = new TraceCodeService();
        $all = collect(array_merge($service->generate($h1, 10), $service->generate($h2, 10)));

        $this->assertCount(20, $all);
        $this->assertSame(20, $all->pluck('code')->unique()->count());
    }

    public function test_villager_cannot_access_admin_trace_codes(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);

        $this->actingAs($user)
            ->get("/admin/trace-codes")
            ->assertForbidden();
    }

    public function test_admin_print_page_shows_qr_script_and_code(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $plot = $this->plot();
        $harvest = $this->makeHarvest($plot);
        $code = TraceCode::create([
            'tenant_id' => $t->id,
            'code' => 'TC20260801-PRINT01',
            'harvest_id' => $harvest->id,
            'plot_id' => $plot->id,
        ]);
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get("/admin/trace-codes/print?ids={$code->id}")
            ->assertOk()
            ->assertSee('qrcode.min.js', false)
            ->assertSee('TC20260801-PRINT01')
            // 打印出的二维码必须是能打开的扫码页（单租户化后 /t/{slug} 前缀已不存在）
            ->assertSee('/s/TC20260801-PRINT01', false)
            ->assertDontSee("/t/{$t->slug}/", false);
    }
}
