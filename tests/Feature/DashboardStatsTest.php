<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\TraceCode;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 3.5 数据看板：认养转化率 / 续费意向 / 产出达标率 / 溯源查看率 在后台首页展示。
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private int $phoneCounter = 0;

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function makeActiveAdopter(): User
    {
        $this->phoneCounter++;
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '1381'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);

        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        return $user;
    }

    public function test_dashboard_shows_kpi_section(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('经营看板')
            ->assertSee('认养转化率')
            ->assertSee('续费意向')
            ->assertSee('产出达标率')
            ->assertSee('溯源查看率');
    }

    public function test_conversion_rate_shown(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $this->makeActiveAdopter(); // 1 生效 / 1 总单 → 100%

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('<div class="num sm">100%</div><div class="label">认养转化率</div>', false);
    }

    public function test_attainment_rate_computed_from_harvest_vs_guarantee(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $plot = $user->adoptions()->first()->adoptable;

        Harvest::create([
            'tenant_id' => $t->id,
            'farm_id' => $this->farm()->id,
            'plot_id' => $plot->id,
            'season_year' => (int) now()->format('Y'),
            'harvested_at' => now()->toDateString(),
            'dry_weight_kg' => 7.5, // 保底 15kg，采收 7.5 → 50%
        ]);

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('<div class="num sm">50%</div><div class="label">产出达标率</div>', false);
    }

    public function test_trace_view_rate_computed_from_codes(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        TraceCode::create(['tenant_id' => $t->id, 'code' => 'TC-A-001', 'scanned_count' => 3]);
        TraceCode::create(['tenant_id' => $t->id, 'code' => 'TC-A-002', 'scanned_count' => 0]);

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('<div class="num sm">50%</div><div class="label">溯源查看率</div>', false); // 2 码 1 被扫 → 50%
    }
}
