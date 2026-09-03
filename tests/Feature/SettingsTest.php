<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SettingsService;
use App\Tenancy\TenantContext;
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
        TenantContext::reset();
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
            ->get("/t/{$t->slug}/admin/settings")
            ->assertOk()
            ->assertSee('品牌主色')
            ->assertSee('SEO');
    }

    public function test_admin_updates_settings_and_layout_reflects_brand(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/t/{$t->slug}/admin/settings", [
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
        $this->get("/t/{$t->slug}")
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
            ->get("/t/{$t->slug}/admin/settings")
            ->assertForbidden();
    }

    public function test_layout_falls_back_to_config_defaults(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();

        $this->get("/t/{$t->slug}")
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
            ->get("/t/{$t->slug}/admin/settings")
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
            ->put("/t/{$t->slug}/admin/settings", [
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
            ->put("/t/{$t->slug}/admin/settings", [
                'rate_partner' => 11, // 超 10% 合规上限
            ])
            ->assertSessionHasErrors('rate_partner');
    }

    public function test_admin_can_update_member_tiers_and_contract_version(): void
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->put("/t/{$t->slug}/admin/settings", [
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
}
