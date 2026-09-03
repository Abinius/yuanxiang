<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\Coupon;
use App\Models\Plot;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\MemberService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M5 会员阶梯：滚动消费 → computeLevel → syncLevel 持久化 + 签约即时升级 + 等级页 + 生日权益。
 */
class MemberTest extends TestCase
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

    private function makeUser(): User
    {
        $this->phoneCounter++;

        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '1382'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
    }

    /** 给用户造一笔生效认养（作为买家，计入滚动消费）。避开同 plot 季节唯一索引。 */
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

    private function setMemberTiers(array $tiers): void
    {
        $tenant = $this->tenant();
        $settings = $tenant->settings ?? [];
        $settings['member']['tiers'] = $tiers;
        $tenant->update(['settings' => $settings]);
    }

    // ---------- 等级判定 ----------

    public function test_level_by_rolling_spend(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setMemberTiers(['red' => 1, 'expert' => 5000, 'partner' => 30000]);
        $service = app(MemberService::class);

        $new = $this->makeUser();
        $red = $this->makeUser();
        $expert = $this->makeUser();
        $partner = $this->makeUser();

        $this->spendFor($red, 1);
        $this->spendFor($expert, 6000);
        $this->spendFor($partner, 35000);

        $this->assertSame(0, $service->computeLevel($new));
        $this->assertSame(1, $service->computeLevel($red));
        $this->assertSame(2, $service->computeLevel($expert));
        $this->assertSame(3, $service->computeLevel($partner));

        $this->assertSame('新人', $service->levelLabel(0));
        $this->assertSame('红人', $service->levelLabel(1));
        $this->assertSame('达人', $service->levelLabel(2));
        $this->assertSame('合伙人', $service->levelLabel(3));
    }

    public function test_sync_level_persists_and_sets_member_since_on_upgrade(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setMemberTiers(['red' => 1, 'expert' => 5000, 'partner' => 30000]);
        $service = app(MemberService::class);
        $user = $this->makeUser();

        $this->assertSame(0, (int) $user->member_level);
        $this->assertNull($user->member_since);

        $this->spendFor($user, 6000);
        $this->assertTrue($service->syncLevel($user));

        $user->refresh();
        $this->assertSame(2, (int) $user->member_level);
        $this->assertNotNull($user->member_since);
    }

    public function test_sync_level_no_change_returns_false(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $service = app(MemberService::class);
        $user = $this->makeUser();

        $this->assertFalse($service->syncLevel($user));
        $this->assertSame(0, (int) $user->fresh()->member_level);
    }

    public function test_sync_level_downgrade_keeps_member_since(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setMemberTiers(['red' => 1, 'expert' => 5000, 'partner' => 30000]);
        $service = app(MemberService::class);
        $user = $this->makeUser();

        $adoption = $this->spendFor($user, 35000); // 升到合伙人
        $service->syncLevel($user);
        $user->refresh();
        $this->assertSame(3, (int) $user->member_level);
        $since = $user->member_since;

        // 消费窗口滚动后降级（模拟：把 start_date 推到 2 年前，不在 365 天内）
        $adoption->update(['start_date' => Carbon::now()->subYears(2)->toDateString()]);
        $changed = $service->syncLevel($user);
        $user->refresh();

        $this->assertTrue($changed);
        $this->assertSame(0, (int) $user->member_level);
        $this->assertSame(
            $since->format('Y-m-d H:i:s'),
            $user->member_since->format('Y-m-d H:i:s'),
            '降级不回退 member_since'
        );
    }

    // ---------- 签约即时升级 ----------

    public function test_sign_agreement_syncs_buyer_level(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->makeUser();
        $this->assertSame(0, (int) $user->member_level);

        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        // plot 认养 annual_fee 5000 → 达人(level 2)
        $user->refresh();
        $this->assertSame(2, (int) $user->member_level);
        $this->assertNotNull($user->member_since);
    }

    // ---------- 等级页 ----------

    public function test_member_page_render(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setMemberTiers(['red' => 1, 'expert' => 5000, 'partner' => 30000]);
        $user = $this->makeUser();
        $this->spendFor($user, 6000);
        app(MemberService::class)->syncLevel($user);
        $t = $this->tenant();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/member")
            ->assertOk()
            ->assertSee('我的会员')
            ->assertSee('达人')
            ->assertSee('当前权益');
    }

    // ---------- 命令 ----------

    public function test_recalculate_command_upgrades_users(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $this->setMemberTiers(['red' => 1, 'expert' => 5000, 'partner' => 30000]);
        $user = $this->makeUser();
        $this->spendFor($user, 35000);
        $this->assertSame(0, (int) $user->member_level);

        $this->artisan('member:recalculate')
            ->assertSuccessful()
            ->expectsOutputToContain('已重算会员等级');

        $user->refresh();
        $this->assertSame(3, (int) $user->member_level);
    }

    public function test_birthday_benefit_command_issues_coupon(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $tenant = $t;
        $settings = $tenant->settings ?? [];
        $settings['member']['birthday_benefit'] = ['promotion_type' => 'renewal'];
        $tenant->update(['settings' => $settings]);

        $promo = Promotion::create([
            'tenant_id' => $t->id, 'name' => '生日券', 'type' => 'renewal',
            'rule' => ['amount' => 500], 'status' => 'active',
        ]);

        $user = $this->makeUser();
        $user->update(['birthday' => now()->format('Y-m-d')]);

        $this->artisan('member:birthday-benefit')
            ->assertSuccessful();

        $this->assertSame(1, Coupon::where('user_id', $user->id)->where('promotion_id', $promo->id)->count());

        // 幂等：再跑不重复
        $this->artisan('member:birthday-benefit')->assertSuccessful();
        $this->assertSame(1, Coupon::where('user_id', $user->id)->where('promotion_id', $promo->id)->count());
    }

    public function test_birthday_benefit_skips_when_not_configured(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->makeUser();
        $user->update(['birthday' => now()->format('Y-m-d')]);

        $this->artisan('member:birthday-benefit')->assertSuccessful();
        $this->assertSame(0, Coupon::where('user_id', $user->id)->count());
    }
}
