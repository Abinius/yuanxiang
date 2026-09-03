<?php

namespace Tests\Unit;

use App\Enums\AdjustmentType;
use App\Models\Adoption;
use App\Models\AdoptionAdjustment;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdjustmentService;
use App\Services\WeChatPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 保底规则引擎纯单元测试：欠收折算退费金额计算、严重欠收判定、幂等、apply 走退款分支。
 * 用 Mockery 隔离 WeChatPayService（不走真实微信退款）。
 */
class AdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function baseData(): array
    {
        $tenant = Tenant::create(['slug' => 'test', 'name' => '测试村', 'status' => 'active']);
        $farm = Farm::create(['tenant_id' => $tenant->id, 'name' => '光彩基地']);
        $user = User::create([
            'tenant_id' => $tenant->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '单元测试',
            'role' => 'villager',
        ]);
        $plan = Plan::create([
            'tenant_id' => $tenant->id,
            'name' => '一分地',
            'price_yearly' => 5000,
            'delivery_rule' => [
                'guarantee_kg' => 15,
                'shortfall' => [
                    'compensate_to_kg' => 15,
                    'severe_threshold_kg' => 10,
                    'refund_price_kg' => 150,
                ],
            ],
        ]);
        $plot = Plot::create([
            'tenant_id' => $tenant->id,
            'farm_id' => $farm->id,
            'plan_id' => $plan->id,
            'type' => 'plot',
            'code' => 'FD-01',
            'status' => 'adopted',
        ]);
        $adoption = Adoption::create([
            'tenant_id' => $tenant->id,
            'adoption_no' => 'AD-UNIT-001',
            'user_id' => $user->id,
            'adoptable_type' => Plot::class,
            'adoptable_id' => $plot->id,
            'plan_id' => $plan->id,
            'farm_id' => $farm->id,
            'season_year' => 2026,
            'annual_fee' => 5000,
            'start_date' => '2026-03-01',
            'status' => 'active',
        ]);

        return compact('tenant', 'plot', 'adoption');
    }

    public function test_丰收不生成补退(): void
    {
        $data = $this->baseData();
        Harvest::create([
            'tenant_id' => $data['tenant']->id,
            'farm_id' => $data['plot']->farm_id,
            'plot_id' => $data['plot']->id,
            'season_year' => 2026,
            'harvested_at' => '2026-09-15',
            'dry_weight_kg' => 20, // >= guarantee 15
        ]);

        $service = new AdjustmentService(Mockery::mock(WeChatPayService::class));
        $result = $service->runForSeason($data['tenant'], 2026);

        $this->assertEmpty($result);
        $this->assertDatabaseEmpty('adoption_adjustments');
    }

    public function test_欠收生成折算退费(): void
    {
        $data = $this->baseData();
        Harvest::create([
            'tenant_id' => $data['tenant']->id,
            'farm_id' => $data['plot']->farm_id,
            'plot_id' => $data['plot']->id,
            'season_year' => 2026,
            'harvested_at' => '2026-09-15',
            'dry_weight_kg' => 12, // < guarantee 15, gap = 3
        ]);

        $service = new AdjustmentService(Mockery::mock(WeChatPayService::class));
        $result = $service->runForSeason($data['tenant'], 2026);

        $this->assertCount(1, $result);
        $adj = $result[0];
        $this->assertEquals(AdjustmentType::RefundProrated->value, $adj->type);
        $this->assertEquals(450.0, $adj->amount); // 3kg * 150 = 450
        $this->assertStringNotContainsString('严重', $adj->reason);
    }

    public function test_严重欠收原因标注(): void
    {
        $data = $this->baseData();
        Harvest::create([
            'tenant_id' => $data['tenant']->id,
            'farm_id' => $data['plot']->farm_id,
            'plot_id' => $data['plot']->id,
            'season_year' => 2026,
            'harvested_at' => '2026-09-15',
            'dry_weight_kg' => 5, // < severe_threshold 10
        ]);

        $service = new AdjustmentService(Mockery::mock(WeChatPayService::class));
        $result = $service->runForSeason($data['tenant'], 2026);

        $this->assertCount(1, $result);
        $this->assertEquals(1500.0, $result[0]->amount); // gap = 15-5 = 10, 10*150 = 1500
        $this->assertStringContainsString('严重', $result[0]->reason);
    }

    public function test_幂等不重复生成(): void
    {
        $data = $this->baseData();
        Harvest::create([
            'tenant_id' => $data['tenant']->id,
            'farm_id' => $data['plot']->farm_id,
            'plot_id' => $data['plot']->id,
            'season_year' => 2026,
            'harvested_at' => '2026-09-15',
            'dry_weight_kg' => 10,
        ]);

        $service = new AdjustmentService(Mockery::mock(WeChatPayService::class));
        $service->runForSeason($data['tenant'], 2026);
        $again = $service->runForSeason($data['tenant'], 2026);

        $this->assertEmpty($again);
        $this->assertDatabaseCount('adoption_adjustments', 1);
    }

    public function test_apply走退款分支并置已应用(): void
    {
        $data = $this->baseData();
        $adj = AdoptionAdjustment::create([
            'tenant_id' => $data['tenant']->id,
            'adoption_id' => $data['adoption']->id,
            'season_year' => 2026,
            'type' => AdjustmentType::RefundProrated->value,
            'amount' => 300.0,
            'reason' => '欠收折算退费',
            'status' => 'pending',
        ]);

        $mockPay = Mockery::mock(WeChatPayService::class);
        $mockPay->shouldReceive('requestRefund')
            ->once()
            ->with(Mockery::on(fn ($a) => true), Mockery::anyOf('欠收折算退费', '缺产折算退费'), 300.0, 'RF-'.$adj->id);

        $service = new AdjustmentService($mockPay);
        $service->apply($adj);

        $adj->refresh();
        $this->assertEquals('applied', $adj->status);
    }
}
