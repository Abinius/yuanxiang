<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 公示协议（认养服务协议 / 隐私政策）：公开可读、文案由 config/site.php 供给、租户设置可覆盖。
 */
class AgreementTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $slug = 'guangcai'): Tenant
    {
        return Tenant::create(['slug' => $slug, 'name' => '陌上原乡', 'status' => 'active']);
    }

    public function test_service_agreement_renders_publicly(): void
    {
        $this->makeTenant();

        $this->get('/agreement/service')
            ->assertOk()
            ->assertSee('陌上原乡认养服务协议')
            ->assertSee(config('site.defaults.agreements.version'))
            ->assertSee('丰欠共担')
            ->assertSee('宁夏花乌巷食品有限公司')
            ->assertSee('相关协议')
            ->assertSee('/agreement/privacy');
    }

    public function test_privacy_agreement_renders_publicly(): void
    {
        $this->makeTenant();

        $this->get('/agreement/privacy')
            ->assertOk()
            ->assertSee('陌上原乡隐私政策')
            ->assertSee('个人信息')
            ->assertSee('肖像');
    }

    public function test_unknown_agreement_key_is_404(): void
    {
        $this->makeTenant();

        $this->get('/agreement/adoption')->assertStatus(404);
    }

    public function test_agreement_links_appear_in_public_footer(): void
    {
        $this->makeTenant();

        $this->get('/')
            ->assertOk()
            ->assertSee('/agreement/service')
            ->assertSee('/agreement/privacy');
    }

    public function test_agreement_text_is_overridable_by_tenant_settings(): void
    {
        $t = $this->makeTenant();
        $t->update(['settings' => ['agreements' => [
            'version' => 'v2',
            'effective' => '2026-11-01',
            'items' => ['service' => [
                'title' => '自定义服务协议',
                'short' => '服务协议',
                'sections' => [['title' => '一、测试', 'body' => ['覆盖文本']]],
            ]],
        ]]]);

        $this->get('/agreement/service')
            ->assertOk()
            ->assertSee('自定义服务协议')
            ->assertSee('覆盖文本')
            ->assertSee('2026-11-01');
    }
}
