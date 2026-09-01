<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
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
}
