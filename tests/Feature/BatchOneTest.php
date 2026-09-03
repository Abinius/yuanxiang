<?php

namespace Tests\Feature;

use App\Enums\FarmLogType;
use App\Models\Adoption;
use App\Models\FarmMember;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\TraceCode;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\TraceCodeService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 第一批（无依赖）：F7 我的丰收 / F6 溯源去重+你的箱 / G3 标题自动 / G8 录入可编辑 / A5 看板可点。
 */
class BatchOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        Cache::flush();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function villager(string $phone = '13800000050'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
            'openid' => 'mock_batch1',
        ]);
    }

    private function makeActiveAdoption(User $user, ?Plot $plot = null): Adoption
    {
        $plot = $plot ?? Plot::where('type', 'plot')->first();
        $this->actingAs($user)
            ->post("/t/{$this->tenant()->slug}/adopt/{$plot->id}/order", [
                'name' => '张三',
                'phone' => '13800000050',
                'province' => '宁夏',
                'city' => '吴忠',
                'district' => '红寺堡',
                'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();
        $adoption = Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
        app(AdoptionService::class)->confirmMockPayment($adoption);
        app(AdoptionService::class)->signAgreement($adoption, '阿林的光彩田');
        return $adoption->fresh();
    }

    private function familyUser(string $scope = 'farm_log', ?string $phone = null): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => $phone ?? '13900000050',
            'password' => 'secret123',
            'nickname' => '阿叔',
            'role' => 'family',
        ]);
        FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $t->farms()->firstOrFail()->id,
            'relation' => 'father',
            'permission_scope' => [$scope],
        ]);
        return $user;
    }

    // ── F7 我的丰收产量面 ────────────────────────────────────

    /** 我的田显示本季累计采收 kg 与历季柱状图。 */
    public function test_my_plot_shows_yield_summary(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);
        $plot = $adoption->adoptable;

        $harvest = Harvest::create([
            'tenant_id' => $t->id, 'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id, 'season_year' => $adoption->season_year,
            'harvested_at' => now()->toDateString(), 'dry_weight_kg' => 12.5,
        ]);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertSee('我的丰收')
            ->assertSee($adoption->season_year)
            ->assertSee('12.5')
            ->assertSee('kg 干果');
    }

    /** 本季无采收时显示空态提示。 */
    public function test_my_plot_yield_empty_state_when_no_harvest(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);

        $this->actingAs($user)
            ->get("/t/{$this->tenant()->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertSee('我的丰收')
            ->assertSee('本季暂无采收记录');
    }

    // ── F6 溯源去重 + 你的箱 ────────────────────────────────

    /** 匿名多次扫码同一码，scanned_count 只 +1（24h 去重）。 */
    public function test_scan_dedupes_anonymous_scans_within_24h(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();
        $harvest = Harvest::create([
            'tenant_id' => $t->id, 'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id, 'season_year' => now()->year,
            'harvested_at' => now()->toDateString(), 'dry_weight_kg' => 10,
        ]);
        $code = collect(app(TraceCodeService::class)->generate($harvest, 1))[0];

        Cache::flush();
        $this->get("/t/{$t->slug}/s/{$code->code}")->assertOk();
        $this->get("/t/{$t->slug}/s/{$code->code}")->assertOk();
        $this->get("/t/{$t->slug}/s/{$code->code}")->assertOk();

        $this->assertSame(1, $code->fresh()->scanned_count);
    }

    /** 扫码人即该箱认养人 → 显示「你的箱」与「查看我的田」链接。 */
    public function test_scan_shows_my_box_for_adopter(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeActiveAdoption($user);
        $plot = $adoption->adoptable;
        $harvest = Harvest::create([
            'tenant_id' => $t->id, 'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id, 'season_year' => now()->year,
            'harvested_at' => now()->toDateString(), 'dry_weight_kg' => 10,
        ]);
        $codes = app(TraceCodeService::class)->generate($harvest, 1, $adoption->id);
        $code = collect($codes)->first();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/s/{$code->code}")
            ->assertOk()
            ->assertSee('你的箱')
            ->assertSee('查看我的田')
            ->assertSee('阿林的光彩田');
    }

    /** 扫码人非认养人 → 显示普通认养人信息，无「你的箱」。 */
    public function test_scan_shows_normal_adopter_info_for_other_user(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $adopter = $this->villager('13800000051');
        $adoption = $this->makeActiveAdoption($adopter);
        $plot = $adoption->adoptable;
        $harvest = Harvest::create([
            'tenant_id' => $t->id, 'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id, 'season_year' => now()->year,
            'harvested_at' => now()->toDateString(), 'dry_weight_kg' => 10,
        ]);
        $codes = app(TraceCodeService::class)->generate($harvest, 1, $adoption->id);
        $code = collect($codes)->first();

        $other = $this->villager('13800000052');
        $this->actingAs($other)
            ->get("/t/{$t->slug}/s/{$code->code}")
            ->assertOk()
            ->assertDontSee('你的箱')
            ->assertDontSee('查看我的田')
            ->assertSee('认养人');
    }

    // ── G3 标题自动生成 ────────────────────────────────────

    /** 家人不填标题时按「类型 · 地块码 · 日期」自动生成。 */
    public function test_family_log_auto_generates_title_when_empty(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $family = $this->familyUser('farm_log');
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($family)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $plot->id,
                'type' => 'fertilize',
                'title' => '',
                'content' => '记录',
                'occurred_at' => now()->toDateString(),
                'is_public' => true,
            ])
            ->assertRedirect();

        $log = FarmLog::latest()->firstOrFail();
        $this->assertSame('fertilize', $log->type->value);
        $this->assertStringContainsString('施肥', $log->title);
        $this->assertStringContainsString($plot->code, $log->title);
    }

    /** 家人填了标题则保留。 */
    public function test_family_log_keeps_user_title(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $family = $this->familyUser('farm_log');
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($family)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $plot->id,
                'type' => 'daily',
                'title' => '今天的田间',
                'is_public' => true,
            ])
            ->assertRedirect();

        $this->assertSame('今天的田间', FarmLog::latest()->firstOrFail()->title);
    }

    // ── G8 录入可编辑 ────────────────────────────────────

    /** 家人可编辑自己录入的农事动态。 */
    public function test_family_can_edit_own_log(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $family = $this->familyUser('farm_log');
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($family)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $plot->id, 'type' => 'daily',
                'title' => '原始', 'is_public' => true,
            ])
            ->assertRedirect();

        $log = FarmLog::latest()->firstOrFail();

        $this->actingAs($family)
            ->post("/t/{$t->slug}/family/logs/{$log->id}", [
                'plot_id' => $plot->id, 'type' => 'daily',
                'title' => '已更新', 'is_public' => true,
            ])
            ->assertRedirect();

        $this->assertSame('已更新', $log->fresh()->title);
    }

    /** 非作者不可编辑。 */
    public function test_family_cannot_edit_others_log(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $author = $this->familyUser('farm_log');
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($author)
            ->post("/t/{$t->slug}/family/logs", [
                'plot_id' => $plot->id, 'type' => 'daily',
                'title' => '作者的', 'is_public' => true,
            ])
            ->assertRedirect();
        $log = FarmLog::latest()->firstOrFail();

        $other = $this->familyUser('farm_log', '13900000051');
        $this->actingAs($other)
            ->post("/t/{$t->slug}/family/logs/{$log->id}", [
                'plot_id' => $plot->id, 'type' => 'daily',
                'title' => '篡改', 'is_public' => true,
            ])
            ->assertNotFound();
    }

    // ── F1 地块故事 ────────────────────────────────────────

    /** 认养详情页显示地块故事卡片。 */
    public function test_plot_detail_shows_story(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->first();
        $plot->update(['story' => '这块田挨着涝坝，夏果格外甜。']);

        $this->get("/t/{$t->slug}/adopt/{$plot->id}")
            ->assertOk()
            ->assertSee('地块故事')
            ->assertSee('这块田挨着涝坝');
    }

    /** admin 可保存地块故事。 */
    public function test_admin_updates_plot_story(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->first();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/t/{$t->slug}/admin/plots/{$plot->id}/story", ['story' => '晨露重，果实甜。'])
            ->assertRedirect();

        $this->assertSame('晨露重，果实甜。', $plot->fresh()->story);
    }

    // ── A5 看板可点 ────────────────────────────────────

    /** 管理员看板顶部统计卡是深度链接。 */
    public function test_admin_dashboard_hero_stats_are_clickable(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get("/t/{$t->slug}/admin")
            ->assertOk()
            ->assertSee('认养转化率')
            ->assertSee('产出达标率')
            ->assertSee('溯源查看率')
            ->assertSee('续费意向')
            ->assertSee(route('tenant.admin.adoptions.index', ['tenant' => $t->slug]))
            ->assertSee(route('tenant.admin.trace-codes.index', ['tenant' => $t->slug]));
    }
}