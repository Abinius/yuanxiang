<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B5 后台集成配置页：权限、保存落库、密钥类字段拒绝、回调地址校验。
 */
class IntegrationsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([BaseSeeder::class, AdminSeeder::class]);

        return User::where('username', 'admin')->firstOrFail();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function villager(): User
    {
        $this->seed([BaseSeeder::class]);

        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '13800000001',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);
    }

    /** 回调地址默认指向当前 APP_URL 主机，保证通过主机校验。 */
    private function notifyUrl(string $scheme = 'https', string $host = null): string
    {
        $host = $host ?? (parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost');

        return $scheme.'://'.$host.'/pay/wechat/notify';
    }

    private function payload(?array $wechat = null, ?array $pay = null): array
    {
        $data = [
            'wechat' => [
                'mock' => '1',
                'app_id' => 'wx-test-appid',
                'scope' => 'snsapi_base',
                'templates' => [
                    'live_notice' => 'TPL-LIVE',
                    'content' => 'TPL-CONTENT',
                    'harvest_notice' => 'TPL-HARVEST',
                    'renewal_notice' => 'TPL-RENEWAL',
                ],
            ],
            'pay' => [
                'mch_id' => '1900000109',
                'mode' => 'normal',
                'notify_url' => $this->notifyUrl(),
            ],
        ];

        if ($wechat !== null) {
            $data['wechat'] = array_merge($data['wechat'], $wechat);
        }
        if ($pay !== null) {
            $data['pay'] = array_merge($data['pay'], $pay);
        }

        return $data;
    }

    public function test_tenant_admin_can_view_page_with_effective_values(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/integrations')
            ->assertOk()
            ->assertSee('微信公众号')
            ->assertSee('模板消息')
            ->assertSee('微信支付')
            ->assertSee('密钥与证书（只读 · 不进后台）')
            ->assertSee('WECHAT_APIV3_KEY')
            ->assertSee('https');
    }

    public function test_villager_cannot_access(): void
    {
        $this->actingAs($this->villager())
            ->get('/admin/integrations')
            ->assertForbidden();
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->seed([BaseSeeder::class]);

        $this->get('/admin/integrations')->assertRedirect('/login');
    }

    public function test_save_persists_non_secret_values_only(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/integrations', $this->payload())
            ->assertRedirect('/admin/integrations')
            ->assertSessionHas('ok');

        $stored = $this->tenant()->settings['integrations'];

        $this->assertTrue($stored['wechat']['mock']);
        $this->assertSame('wx-test-appid', $stored['wechat']['app_id']);
        $this->assertSame('snsapi_base', $stored['wechat']['scope']);
        $this->assertSame(['TPL-LIVE', 'TPL-CONTENT', 'TPL-HARVEST', 'TPL-RENEWAL'], array_values($stored['wechat']['templates']));
        $this->assertSame('1900000109', $stored['pay']['mch_id']);
        $this->assertSame('normal', $stored['pay']['mode']);
        $this->assertSame($this->notifyUrl(), $stored['pay']['notify_url']);

        // 方案 A：密钥类配置一个字符都不落库
        $json = json_encode($stored, JSON_UNESCAPED_SLASHES);
        foreach (['secret', 'api_v3', 'mch_secret_key', 'mch_secret_cert', 'mch_public_cert_path', 'apiclient'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_mock_field_is_optional_and_defaults_off(): void
    {
        // 复选框未勾选、或生产环境只读控件不提交时都会缺这个字段，服务端不能因此 422
        $payload = $this->payload();
        unset($payload['wechat']['mock']);

        $this->actingAs($this->admin())
            ->put('/admin/integrations', $payload)
            ->assertRedirect('/admin/integrations')
            ->assertSessionHas('ok');

        $this->assertFalse($this->tenant()->settings['integrations']['wechat']['mock']);
    }

    public function test_secret_fields_are_rejected_even_if_posted(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/integrations', $this->payload(['secret' => 'leaked-secret'], ['mch_secret_key' => 'leaked-key']))
            ->assertSessionHasErrors(['wechat.secret', 'pay.mch_secret_key']);

        $this->assertNull($this->tenant()->settings['integrations'] ?? null);
    }

    public function test_notify_url_must_be_https_on_app_host(): void
    {
        $admin = $this->admin();

        // url 规则也认 ftp/ws/gopher，光拦小写 http:// 等于没拦
        foreach ([
            $this->notifyUrl('http'),
            $this->notifyUrl('ftp'),
            $this->notifyUrl('HTTP'),
            $this->notifyUrl('https', 'evil.example.com'),
        ] as $url) {
            $this->actingAs($admin)
                ->put('/admin/integrations', $this->payload(null, ['notify_url' => $url]))
                ->assertSessionHasErrors('pay.notify_url');
        }

        $this->assertNull($this->tenant()->settings['integrations'] ?? null);
    }

    public function test_invalid_scope_and_mode_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/admin/integrations', $this->payload(['scope' => 'snsapi_any']))
            ->assertSessionHasErrors('wechat.scope');

        $this->actingAs($admin)
            ->put('/admin/integrations', $this->payload(null, ['mode' => 'partner']))
            ->assertSessionHasErrors('pay.mode');
    }
}
