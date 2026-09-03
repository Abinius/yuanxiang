<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\CommissionLedger;
use App\Models\Coupon;
use App\Models\Payout;
use App\Models\Plot;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\CommissionService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * M4 分销佣金：推荐关系 → 认养签约记账 → 冷却期转正 → 提现 → admin 审核/驳回回流 → 退款冻结。
 */
class CommissionTest extends TestCase
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

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function makeUser(): User
    {
        $this->phoneCounter++;

        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '1381'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
    }

    /** 走完整下单+支付+签约，返回生效认养。推荐人在签约前注入（credit 在 signAgreement 内触发）。 */
    private function makeActiveAdoption(User $user, ?User $referrer = null): Adoption
    {
        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        if ($referrer) {
            $adoption->update(['referred_by_user_id' => $referrer->id]);
        }
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        return $adoption->fresh();
    }

    /** 给用户造一笔生效认养（作为买家，计入滚动消费）。避开同一 plot 季节唯一索引。 */
    private function spendFor(User $user, float $fee): Adoption
    {
        $t = $this->tenant();
        $taken = Adoption::where('tenant_id', $t->id)->where('adoptable_type', Plot::class)->pluck('adoptable_id');
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->whereNotIn('id', $taken)->orderBy('id')->firstOrFail();

        return Adoption::create([
            'tenant_id' => $t->id,
            'adoption_no' => 'T'.uniqid(),
            'user_id' => $user->id,
            'adoptable_type' => Plot::class,
            'adoptable_id' => $plot->id,
            'plan_id' => $plot->plan_id,
            'season_year' => (int) now()->format('Y'),
            'annual_fee' => $fee,
            'start_date' => now()->toDateString(),
            'status' => AdoptionStatus::Active->value,
        ]);
    }

    /** 设置租户佣金配置（费率/冷却期）。 */
    private function setCommission(array $overrides = []): void
    {
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];
        $settings['commission'] = array_merge([
            'rates' => ['red' => 5, 'expert' => 7, 'partner' => 10],
            'cooldown_days' => 7,
        ], $overrides);
        $tenant->update(['settings' => $settings]);
    }

    // ---------- 记账 ----------

    public function test_credit_created_on_sign_agreement(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setCommission();
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);

        $ledger = $adoption->commissionLedger()->first();
        $this->assertNotNull($ledger);
        $this->assertSame('red', $ledger->tier);
        $this->assertSame(5.0, (float) $ledger->rate);
        $this->assertSame(round((float) $adoption->annual_fee * 0.05, 2), (float) $ledger->amount);
        $this->assertSame('pending', $ledger->status);
    }

    public function test_credit_idempotent(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setCommission();
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);

        app(CommissionService::class)->credit($adoption);
        app(CommissionService::class)->credit($adoption);

        $this->assertSame(1, CommissionLedger::where('adoption_id', $adoption->id)->count());
    }

    public function test_no_referrer_no_commission(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer);

        $this->assertNull($adoption->commissionLedger()->first());
    }

    // ---------- tier 判定 ----------

    public function test_tier_by_rolling_spend(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $service = app(CommissionService::class);

        $red = $this->makeUser();
        $expert = $this->makeUser();
        $partner = $this->makeUser();

        $this->spendFor($red, 3000);
        $this->spendFor($expert, 6000);
        $this->spendFor($partner, 20000);
        $this->spendFor($partner, 15000);

        $this->assertSame('red', $service->tierOf($red));
        $this->assertSame('expert', $service->tierOf($expert));
        $this->assertSame('partner', $service->tierOf($partner));

        // 佣金率跟着 tier 走
        $this->assertSame(5.0, $service->rateOf($red));
        $this->assertSame(7.0, $service->rateOf($expert));
        $this->assertSame(10.0, $service->rateOf($partner));
    }

    // ---------- 冷却期转正 ----------

    public function test_cooldown_settles_pending_to_available(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setCommission(['cooldown_days' => 7]);
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);

        $ledger = $adoption->commissionLedger()->first();
        $this->assertSame('pending', $ledger->status);

        // 未到冷却期不动
        app(CommissionService::class)->settleByCooldown();
        $this->assertSame('pending', $ledger->fresh()->status);

        // 超过冷却期 → available（created_at 不在 fillable，需直接赋值后 save）
        $ledger->created_at = Carbon::now()->subDays(8);
        $ledger->save();
        app(CommissionService::class)->settleByCooldown();
        $this->assertSame('available', $ledger->fresh()->status);
    }

    // ---------- 提现 ----------

    public function test_cash_out_creates_payout_and_marks_settled(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setCommission(['cooldown_days' => 0]);
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);
        $ledger = $adoption->commissionLedger()->first();
        $ledger->update(['created_at' => Carbon::now()->subDays(1), 'status' => 'available']);

        $service = app(CommissionService::class);
        $balance = $service->availableBalance($referrer);
        $this->assertGreaterThan(0, $balance);

        $payout = $service->cashOut($referrer, $balance);

        $this->assertSame('commission', $payout->type);
        $this->assertSame('pending', $payout->status);
        $this->assertSame(round($balance, 2), (float) $payout->amount);
        $this->assertSame(0.0, $service->availableBalance($referrer));
        $this->assertSame('settled', $ledger->fresh()->status);
        $this->assertSame($payout->id, $ledger->fresh()->payout_id);
    }

    public function test_cash_out_rejects_insufficient_balance(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->makeUser();

        $this->expectException(HttpException::class);
        app(CommissionService::class)->cashOut($user, 100);
    }

    public function test_commission_pages_render(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $this->setCommission();
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);
        $t = $this->tenant();

        $this->actingAs($referrer)
            ->get("/t/{$t->slug}/my/referral")
            ->assertOk()
            ->assertSee('佣金账户')
            ->assertSee('推荐业绩');

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/commissions")
            ->assertOk()
            ->assertSee('佣金与提现');
    }

    // ---------- admin 审核 ----------

    public function test_admin_approve_marks_paid(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $this->setCommission(['cooldown_days' => 0]);
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);
        $ledger = $adoption->commissionLedger()->first();
        $ledger->update(['created_at' => Carbon::now()->subDays(1), 'status' => 'available']);
        $payout = app(CommissionService::class)->cashOut($referrer, (float) $ledger->amount);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/commissions/payouts/{$payout->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('ok');

        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertNotNull($payout->fresh()->paid_at);
    }

    public function test_admin_reject_refunds_commission(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $this->setCommission(['cooldown_days' => 0]);
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);
        $ledger = $adoption->commissionLedger()->first();
        $ledger->update(['created_at' => Carbon::now()->subDays(1), 'status' => 'available']);
        $payout = app(CommissionService::class)->cashOut($referrer, (float) $ledger->amount);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/commissions/payouts/{$payout->id}/reject")
            ->assertRedirect()
            ->assertSessionHas('ok');

        // 提现失败 + 佣金回流
        $this->assertSame('failed', $payout->fresh()->status);
        $this->assertSame('available', $ledger->fresh()->status);
        $this->assertSame((float) $ledger->amount, app(CommissionService::class)->availableBalance($referrer));
    }

    // ---------- 退款冻结 ----------

    public function test_refund_freezes_pending_commission(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setCommission();
        $referrer = $this->makeUser();
        $buyer = $this->makeUser();
        $adoption = $this->makeActiveAdoption($buyer, $referrer);
        $ledger = $adoption->commissionLedger()->first();
        $this->assertSame('pending', $ledger->status);

        app(AdoptionService::class)->markRefunded($adoption);

        $this->assertSame('frozen', $ledger->fresh()->status);
        $this->assertSame(AdoptionStatus::Cancelled->value, $adoption->fresh()->status->value);
    }

    // ---------- 端到端：推荐码 → 认养 → 佣金 ----------

    public function test_referral_code_end_to_end_credits_commission(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setCommission();
        $t = $this->tenant();

        // 推荐人持 referral 券，配置好两级奖励
        $referrer = $this->makeUser();
        $referralPromo = Promotion::create([
            'tenant_id' => $t->id, 'name' => '推荐有礼', 'type' => 'referral', 'status' => 'active',
        ]);
        Coupon::create([
            'tenant_id' => $t->id, 'user_id' => $referrer->id,
            'promotion_id' => $referralPromo->id, 'code' => 'REF-E2E-01', 'status' => 'unused',
        ]);
        Promotion::create([
            'tenant_id' => $t->id, 'name' => '新客立减', 'type' => 'new_customer', 'rule' => ['amount' => 300], 'status' => 'active',
        ]);
        Promotion::create([
            'tenant_id' => $t->id, 'name' => '老客回馈', 'type' => 'renewal', 'rule' => ['amount' => 300], 'status' => 'active',
        ]);

        $buyer = $this->makeUser();
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();

        // 下单带推荐码 → 签约 → 佣金记账
        $this->actingAs($buyer)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", [
                'name' => '李四', 'phone' => '13800000002', 'province' => '宁夏',
                'city' => '吴忠', 'district' => '红寺堡', 'detail' => '3 号',
                'referral_code' => 'REF-E2E-01',
            ])
            ->assertRedirect();

        $adoption = Adoption::where('adoptable_id', $plot->id)->where('user_id', $buyer->id)->latest('id')->firstOrFail();
        $this->assertSame($referrer->id, $adoption->referred_by_user_id);

        app(AdoptionService::class)->confirmMockPayment($adoption);
        app(AdoptionService::class)->signAgreement($adoption, '端到端的田');

        $ledger = CommissionLedger::where('adoption_id', $adoption->id)->first();
        $this->assertNotNull($ledger);
        $this->assertSame($referrer->id, $ledger->user_id);
        $this->assertSame('pending', $ledger->status);
    }

    public function test_villager_cannot_access_admin_commission_page(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/commissions")
            ->assertForbidden();
    }
}
