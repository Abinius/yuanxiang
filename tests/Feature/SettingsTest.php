<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 分享与站点设置：租户设置页 + 品牌色/SEO 文案落到 tenants.settings 并反映到布局。
 */
class SettingsTest extends TestCase
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

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_admin_can_edit_settings(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->get("/admin/settings")
            ->assertOk()
            ->assertSee('品牌主色')
            ->assertSee('SEO');
    }

    public function test_admin_updates_settings_and_layout_reflects_brand(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/admin/settings", [
                'brand_primary' => '#123456',
                'brand_accent' => '#654321',
                'seo_title' => '测试站点',
                'seo_description' => '测试描述文案',
                'footer_copyright' => '测试主体',
            ])
            ->assertRedirect();

        $t->refresh();
        $this->assertSame('#123456', $t->settings['brand']['primary']);
        $this->assertSame('测试描述文案', $t->settings['seo']['description']);

        // 前台布局 :root 品牌色 + SEO description 即时生效
        $this->get("")
            ->assertOk()
            ->assertSee('--primary:#123456', false)
            ->assertSee('测试描述文案');
    }

    public function test_villager_cannot_edit_settings(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $villager = User::create([
            'tenant_id' => $t->id, 'phone' => '13800000001', 'password' => 'secret123',
            'nickname' => '云乡民', 'role' => 'villager',
        ]);

        $this->actingAs($villager)
            ->get("/admin/settings")
            ->assertForbidden();
    }

    public function test_layout_falls_back_to_config_defaults(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("")
            ->assertOk()
            ->assertSee('og:title', false)
            ->assertSee(config('site.defaults.description'));
    }

    // ── M2：定价 / 营销 / 分销 / 会员 / 合同 后台可设 ──

    public function test_settings_form_shows_pricing_and_commission_sections(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->get("/admin/settings")
            ->assertOk()
            ->assertSee('分地档年费')
            ->assertSee('保底产量')
            ->assertSee('红人佣金率')
            ->assertSee('会员阶梯');
    }

    public function test_admin_can_update_pricing_settings(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/admin/settings", [
                'fendi_yearly' => 6000,
                'zhu_yearly' => 360,
                'guarantee_fendi' => 20,
                'guarantee_zhu' => 0.6,
            ])
            ->assertRedirect();

        $t->refresh();
        $this->assertSame(6000, $t->settings['pricing']['fendi_yearly']);
        $this->assertSame(360, $t->settings['pricing']['zhu_yearly']);
        $this->assertEquals(20, $t->settings['pricing']['guarantee_kg']['fendi']);
        $this->assertEquals(0.6, $t->settings['pricing']['guarantee_kg']['zhu']);
    }

    public function test_settings_service_two_layer_override(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $svc = app(SettingsService::class);

        // 无覆盖 → config 默认
        $this->assertSame(config('site.defaults.pricing.fendi_yearly'), $svc->pricing($t)['fendi_yearly']);
        $this->assertSame(config('site.defaults.commission.rates.partner'), $svc->commission($t)['rates']['partner']);

        // 覆盖后 → 取租户值
        $t->settings = ['pricing' => array_merge(config('site.defaults.pricing'), ['fendi_yearly' => 7777])];
        $t->save();

        $this->assertSame(7777, $svc->pricing($t)['fendi_yearly']);
        $this->assertSame(config('site.defaults.pricing.zhu_yearly'), $svc->pricing($t)['zhu_yearly']); // 未覆盖项回落
    }

    public function test_commission_rate_capped_at_10_percent(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/admin/settings", [
                'rate_partner' => 11, // 超 10% 合规上限
            ])
            ->assertSessionHasErrors('rate_partner');
    }

    public function test_admin_can_update_member_tiers_and_contract_version(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/admin/settings", [
                'tier_red' => 1,
                'tier_expert' => 8888,
                'tier_partner' => 50000,
                'contract_template_version' => 'v2',
            ])
            ->assertRedirect();

        $t->refresh();
        $this->assertSame(8888, $t->settings['member']['tiers']['expert']);
        $this->assertSame('v2', $t->settings['contract']['template_version']);
    }

    // ── R4-a：首页内容配置化 ──

    public function test_home_renders_defaults_when_no_settings(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("")
            ->assertOk()
            ->assertSee(config('site.defaults.homepage.hero_title'))
            // {tenant} 占位被替换成站点名
            ->assertSee('这里是 '.$t->name)
            ->assertSee(config('site.defaults.homepage.points_title'))
            ->assertSee(config('site.defaults.homepage.flow_title'))
            ->assertSee(config('site.defaults.homepage.faq_title'));
    }

    public function test_settings_form_shows_homepage_section(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);

        $this->actingAs($this->admin())
            ->get("/admin/settings")
            ->assertOk()
            ->assertSee('首页内容')
            ->assertSee('显示「卖点」区块')
            ->assertSee(config('site.defaults.homepage.points.0.title'))
            ->assertSee('＋ 加一条问答（最多 10 条）');
    }

    public function test_admin_can_save_homepage_settings_and_home_reflects(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/admin/settings", [
                'show_points' => '1', 'show_flow' => '1', 'show_faq' => '1',
                'hero_title' => '测试主标题',
                'hero_lede' => '这里是 {tenant}，测试副标题。',
                'points_title' => '测试卖点标题',
                'points' => [['title' => '测试卖点', 'body' => '测试卖点说明', 'note' => '']],
                'flow_steps' => [['num' => '01', 'label' => '测试步骤', 'note' => '']],
                'faq' => [['q' => '测试问题', 'a' => '']],
            ])
            ->assertRedirect();

        $t->refresh();
        $this->assertSame('测试主标题', $t->settings['homepage']['hero_title']);
        $this->assertSame('测试卖点', $t->settings['homepage']['points'][0]['title']);
        $this->assertCount(1, $t->settings['homepage']['points'], '删掉的出厂行不应残留');

        $this->get("")
            ->assertOk()
            ->assertSee('测试主标题')
            ->assertSee('这里是 '.$t->name.'，测试副标题。')
            ->assertSee('测试卖点说明')
            ->assertDontSee(config('site.defaults.homepage.points.1.title'), false);
    }

    public function test_home_falls_back_per_field_when_only_partly_overridden(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $t->settings = ['homepage' => ['hero_title' => '只改标题']];
        $t->save();

        $this->get("")
            ->assertOk()
            ->assertSee('只改标题')
            // 未覆盖的区块仍是出厂文案
            ->assertSee('认养后可在「我的田」查看生长日历')
            ->assertSee(config('site.defaults.homepage.points.0.title'));
    }

    public function test_hidden_section_is_not_rendered(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        // 不勾「认养流程」= 该字段缺键，区块应整体不渲染（而不是渲染空标题）
        $t->settings = ['homepage' => ['show_flow' => false]];
        $t->save();

        $this->get("")
            ->assertOk()
            ->assertDontSee(config('site.defaults.homepage.flow_title'))
            ->assertDontSee(config('site.defaults.homepage.flow_steps.0.label'))
            ->assertSee(config('site.defaults.homepage.points_title'));
    }

    public function test_homepage_xss_is_escaped(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $t->settings = ['homepage' => ['hero_title' => '<script>alert(1)</script>']];
        $t->save();

        $this->get("")
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_homepage_row_limits_are_enforced(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $seven = array_map(fn ($i) => ['title' => '第'.$i.'条', 'body' => '说明'], range(1, 7));

        $this->actingAs($this->admin())
            ->put("/admin/settings", ['points' => $seven])
            ->assertSessionHasErrors('points');

        $this->assertNull($t->fresh()->settings['homepage'] ?? null, '超限不应写入');
    }
}
