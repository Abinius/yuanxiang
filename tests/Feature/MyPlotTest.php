<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Enums\FarmLogType;
use App\Models\Adoption;
use App\Models\FarmLog;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2.2 我的田：铭牌 + 生长日历 + 农事动态流 + 铭牌分享。
 * 访问控制（主人/非主人/状态）+ 动态流（公开/私有/倒序/跨田隔离）+ 日历 + 铭牌页 + 列表。
 */
class MyPlotTest extends TestCase
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
            'name' => '张三',
            'phone' => '13800000001',
            'province' => '宁夏',
            'city' => '吴忠',
            'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ];
    }

    /** 下单（pending_payment）。 */
    private function makeOrder(User $user): Adoption
    {
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", $this->orderData())
            ->assertRedirect();

        return Adoption::where('adoptable_id', $plot->id)->firstOrFail();
    }

    /** 下单 → mock 支付 → 签约命名 → 生效。 */
    private function makeActiveAdoption(User $user, string $label = '阿林的光彩田'): Adoption
    {
        $adoption = $this->makeOrder($user);

        $service = app(AdoptionService::class);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, $label);

        return $adoption->fresh();
    }

    private function makeLog(Adoption $adoption, FarmLogType $type, string $title, bool $public = true, ?string $occurredAt = null): FarmLog
    {
        $plot = $adoption->adoptable;

        return FarmLog::create([
            'tenant_id' => $adoption->tenant_id,
            'farm_id' => $adoption->farm_id,
            'plot_id' => $plot->id,
            'type' => $type->value,
            'title' => $title,
            'content' => '记录内容',
            'occurred_at' => $occurredAt ?? now()->subDay(),
            'is_public' => $public,
            'source' => 'family',
        ]);
    }

    // ── 访问控制 ──────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();

        // 经服务层建单（不经 actingAs，保持 guest 状态）
        $service = app(AdoptionService::class);
        $adoption = $service->createOrder($user, Plot::where('type', 'plot')->first(), $this->orderData());
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '阿林的光彩田');

        $this->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertRedirect("/t/{$t->slug}/login");
    }

    public function test_owner_sees_nameplate_label_and_plot_code(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertSee($adoption->named_label)
            ->assertSee($adoption->adoptable->code);
    }

    public function test_non_owner_gets_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $owner = $this->villager('13800000001');
        $other = $this->villager('13800000002');
        $adoption = $this->makeActiveAdoption($owner);

        $this->actingAs($other)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertNotFound();
    }

    public function test_non_active_status_is_forbidden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeOrder($user); // pending_payment
        app(AdoptionService::class)->confirmMockPayment($adoption); // → pending_agreement

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertForbidden();
    }

    // ── 农事动态流 ────────────────────────────────────────────

    public function test_activity_stream_shows_public_only_in_desc_order(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        $this->makeLog($adoption, FarmLogType::Daily, '旧记录', true, now()->subDays(10));
        $this->makeLog($adoption, FarmLogType::Weed, '私密除草', false, now()->subDays(2));
        $this->makeLog($adoption, FarmLogType::Fertilize, '近期施肥', true, now()->subDays(1));

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertSeeInOrder(['近期施肥', '旧记录'])
            ->assertSee('近期施肥')
            ->assertSee('旧记录')
            ->assertDontSee('私密除草');
    }

    public function test_activity_stream_isolates_other_plots(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        // 另一块分地（FD-02）
        $otherPlot = Plot::where('type', 'plot')->orderBy('id')->skip(1)->first();

        FarmLog::create([
            'tenant_id' => $adoption->tenant_id,
            'farm_id' => $adoption->farm_id,
            'plot_id' => $otherPlot->id,
            'type' => FarmLogType::Harvest->value,
            'title' => '别家的采收',
            'content' => '不应出现',
            'occurred_at' => now(),
            'is_public' => true,
            'source' => 'family',
        ]);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertDontSee('别家的采收');
    }

    // ── 生长日历 ──────────────────────────────────────────────

    public function test_growth_calendar_has_today_marker_and_current_stage(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        $currentStage = config('goji.stages')[(int) now()->format('n')]['label'];

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertSee('今天')
            ->assertSee($currentStage);
    }

    // ── 铭牌分享页 + 列表 ─────────────────────────────────────

    public function test_nameplate_page_is_ok_and_shows_label(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}/nameplate")
            ->assertOk()
            ->assertSee($adoption->named_label)
            ->assertSee($adoption->adoptable->code);
    }

    public function test_my_index_lists_adoptions(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee($adoption->named_label);
    }

    public function test_my_index_shows_resume_action_for_pending_payment(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $this->makeOrder($user); // pending_payment

        // 断单续接：非生效单不再死链 403，而是「去支付」
        $this->actingAs($user)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee('去支付');
    }

    public function test_my_index_shows_sign_action_for_pending_agreement(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);
        app(AdoptionService::class)->confirmMockPayment($adoption); // → pending_agreement

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee('去签署协议');
    }

    public function test_pay_page_shows_sign_action_for_pending_agreement(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);
        app(AdoptionService::class)->confirmMockPayment($adoption); // → pending_agreement

        $this->actingAs($user)
            ->get("/t/{$t->slug}/adopt/order/{$adoption->id}/pay")
            ->assertOk()
            ->assertSee('去签署协议');
    }
}
