<?php

namespace Tests\Feature;

use App\Models\AdoptionAdjustment;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\AdjustmentService;
use App\Services\WeChatPayService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 3.2 保底规则引擎：欠收折算退费（¥150/kg）、严重欠收、平年不记录、幂等结算、
 * apply（部分退款 mock）、权限/租户隔离。
 */
class AdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
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

    private function makeHarvest(Plot $plot, float $kg): Harvest
    {
        return Harvest::create([
            'tenant_id' => $plot->tenant_id,
            'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id,
            'season_year' => (int) now()->format('Y'),
            'harvested_at' => now()->toDateString(),
            'dry_weight_kg' => $kg,
        ]);
    }

    public function test_shortfall_creates_refund_prorated(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $plot = $user->adoptions()->first()->adoptable;
        $this->makeHarvest($plot, 12); // 保底 15 → 差 3kg × ¥150 = ¥450

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();

        $adjustment = AdoptionAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertSame('refund_prorated', $adjustment->type);
        $this->assertEquals(450.00, (float) $adjustment->amount);
        $this->assertSame('pending', $adjustment->status);
        $this->assertStringContainsString('欠收', $adjustment->reason);
    }

    public function test_severe_shortfall_marks_severe_reason(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $plot = $user->adoptions()->first()->adoptable;
        $this->makeHarvest($plot, 5); // < 10 严重欠收 → 差 10kg × 150 = ¥1500

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();

        $adjustment = AdoptionAdjustment::first();
        $this->assertEquals(1500.00, (float) $adjustment->amount);
        $this->assertStringContainsString('严重', $adjustment->reason);
        $this->assertStringContainsString('下季优先', $adjustment->reason);
    }

    public function test_normal_year_creates_no_adjustment(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $this->makeHarvest($user->adoptions()->first()->adoptable, 15); // ≥ 保底

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();

        $this->assertSame(0, AdoptionAdjustment::count());
    }

    public function test_settle_is_idempotent(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $this->makeHarvest($user->adoptions()->first()->adoptable, 10);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();
        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();

        $this->assertSame(1, AdoptionAdjustment::count());
    }

    public function test_apply_marks_applied_with_mock_refund(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();
        $this->makeHarvest($adoption->adoptable, 12);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();

        $adjustment = AdoptionAdjustment::first();
        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/{$adjustment->id}/apply")
            ->assertRedirect();

        $this->assertSame('applied', $adjustment->fresh()->status);
    }

    public function test_apply_calls_refund_with_prorated_amount_and_stable_refund_no(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $this->makeHarvest($user->adoptions()->first()->adoptable, 12); // 差 3kg × ¥150 = ¥450

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();

        $adjustment = AdoptionAdjustment::first();

        // P3：断言部分退款金额=¥450 且 out_refund_no 确定性（防重试二次退费）
        $pay = Mockery::mock(WeChatPayService::class);
        $pay->shouldReceive('requestRefund')
            ->once()
            ->with(
                Mockery::on(fn ($adoption) => $adoption->id === $adjustment->adoption_id),
                Mockery::type('string'),
                Mockery::on(fn ($amount) => abs((float) $amount - 450.0) < 0.001),
                Mockery::on(fn ($refundNo) => $refundNo === 'RF-'.$adjustment->id),
            )
            ->andReturn(null);

        // 直接构造服务注入 mock（HTTP 全链路由 test_apply_marks_applied_with_mock_refund 覆盖）
        (new AdjustmentService($pay))->apply($adjustment);

        $this->assertSame('applied', $adjustment->fresh()->status);
    }

    public function test_reapplying_applied_adjustment_fails(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $this->makeHarvest($user->adoptions()->first()->adoptable, 12);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/settle", ['season_year' => now()->year])
            ->assertRedirect();
        $adjustment = AdoptionAdjustment::first();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/{$adjustment->id}/apply")
            ->assertRedirect();
        $this->assertSame('applied', $adjustment->fresh()->status);

        // P3：已应用再应用 → 422（幂等守卫，不会二次退费）
        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/{$adjustment->id}/apply")
            ->assertStatus(422);
    }

    public function test_villager_forbidden_and_cross_tenant_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();

        // villager → 403
        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/adjustments")
            ->assertForbidden();

        // 跨租户 adjustment → apply 404
        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);
        $otherAdjustment = AdoptionAdjustment::create([
            'tenant_id' => $other->id,
            'adoption_id' => $adoption->id,
            'season_year' => now()->year,
            'type' => 'refund_prorated',
            'amount' => 100,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adjustments/{$otherAdjustment->id}/apply")
            ->assertNotFound();
    }
}
