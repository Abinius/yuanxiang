<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Contract;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M3 认养合同：签约生成合同 + 查看 + 跨用户隔离 + P0 保底数据源。
 */
class ContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function villager(string $phone = '13800000001'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);
    }

    private function orderData(): array
    {
        return [
            'name' => '张三', 'phone' => '13800000001',
            'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ];
    }

    /** 下单 → mock 支付 → 路由签约（带 IP）→ 生效 + 生成合同。 */
    private function signActiveAdoption(User $user, string $label = '阿林的光彩田'): Adoption
    {
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", $this->orderData())
            ->assertRedirect();

        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();
        app(AdoptionService::class)->confirmMockPayment($adoption);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/order/{$adoption->id}/sign", ['named_label' => $label])
            ->assertRedirect();

        return $adoption->fresh();
    }

    public function test_signing_creates_contract(): void
    {
        $adoption = $this->signActiveAdoption($this->villager());

        $this->assertDatabaseCount('contracts', 1);
        $contract = Contract::where('adoption_id', $adoption->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^2026-guangcai-\d{4}$/', $contract->contract_no);
        $this->assertSame('v1', $contract->template_version);
        $this->assertIsArray($contract->clauses);
        $this->assertNotEmpty($contract->clauses);
        $this->assertNotNull($contract->signed_at);
        $this->assertNotNull($contract->signed_ip); // 路由签约带 IP
        // 条款含保底（一分地 plan guarantee_kg=15）
        $guaranteeClause = collect($contract->clauses)->firstWhere('title', '保底产量与丰欠共担');
        $this->assertStringContainsString('15', $guaranteeClause['body']);
    }

    public function test_contract_view_accessible_by_owner(): void
    {
        $user = $this->villager();
        $adoption = $this->signActiveAdoption($user);
        $t = $this->tenant();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}/contract")
            ->assertOk()
            ->assertSee('认养合同')
            ->assertSee($adoption->contract->contract_no);
    }

    public function test_contract_view_404_for_non_owner(): void
    {
        $owner = $this->villager('13800000001');
        $adoption = $this->signActiveAdoption($owner);
        $other = $this->villager('13800000002');
        $t = $this->tenant();

        $this->actingAs($other)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}/contract")
            ->assertNotFound();
    }

    public function test_unsigned_adoption_contract_404(): void
    {
        $user = $this->villager();
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        // 下单但未签约
        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", $this->orderData());
        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}/contract")
            ->assertNotFound();
    }

    public function test_plot_guarantee_kg_from_plan(): void
    {
        $fendi = Plot::where('type', 'plot')->first();
        $this->assertSame(15.0, $fendi->guaranteeKg());

        $zhu = Plot::where('type', 'plant')->first();
        $this->assertSame(0.5, $zhu->guaranteeKg());
    }
}
