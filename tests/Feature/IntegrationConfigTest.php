<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\WechatTemplateService;
use App\Support\IntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Yansongda\Pay\Pay;

/**
 * B5 集成配置：后台填的值覆盖 env，空值回落 env；三个调用点（全局 web 中间件、
 * web 组之外的 notify 路由、模板推送服务）都必须生效。
 */
class IntegrationConfigTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        $tenant = Tenant::create(['slug' => 'guangcai', 'name' => '光彩云村庄', 'status' => 'active']);

        return $tenant;
    }

    public function test_stored_values_override_env(): void
    {
        $tenant = $this->tenant();

        $tenant->update(['settings' => ['integrations' => [
            'wechat' => ['mock' => true, 'app_id' => 'wx-stored', 'scope' => 'snsapi_base', 'templates' => []],
            'pay' => ['mch_id' => '1900000109', 'mode' => 'service', 'notify_url' => 'https://example.com/pay/wechat/notify'],
        ]]]);

        IntegrationConfig::apply();

        $this->assertTrue(config('wechat.mock'));
        $this->assertSame('wx-stored', config('wechat.app_id'));
        // 支付下单读的是 mp_app_id，必须与登录用的 app_id 同源，否则 openid 与 appid 分叉
        $this->assertSame('wx-stored', config('pay.wechat.default.mp_app_id'));
        $this->assertSame('snsapi_base', config('wechat.scope'));
        $this->assertSame('1900000109', config('pay.wechat.default.mch_id'));
        // 必须是整型常量：yansongda 拿这个值当 URL 表下标，也用它做严格比较
        $this->assertSame(Pay::MODE_SERVICE, config('pay.wechat.default.mode'));
        $this->assertSame('https://example.com/pay/wechat/notify', config('pay.wechat.default.notify_url'));
    }

    public function test_mode_is_translated_to_yansongda_constants(): void
    {
        $tenant = $this->tenant();

        $tenant->update(['settings' => ['integrations' => ['pay' => ['mode' => 'normal']]]]);
        IntegrationConfig::apply();
        $this->assertSame(Pay::MODE_NORMAL, config('pay.wechat.default.mode'));

        IntegrationConfig::reset();
        $tenant->update(['settings' => ['integrations' => ['pay' => ['mode' => 'service']]]]);
        IntegrationConfig::apply();
        $this->assertSame(Pay::MODE_SERVICE, config('pay.wechat.default.mode'));
    }

    public function test_empty_values_fall_back_to_env(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['settings' => ['integrations' => [
            'wechat' => ['app_id' => '', 'scope' => ''],
            'pay' => ['mch_id' => '', 'notify_url' => ''],
        ]]]);

        config()->set('wechat.app_id', 'wx-from-env');
        config()->set('pay.wechat.default.mch_id', '1900000000');

        IntegrationConfig::apply();

        // 空值不能把 env 值冲掉，也不能把 config 置空
        $this->assertSame('wx-from-env', config('wechat.app_id'));
        $this->assertSame('1900000000', config('pay.wechat.default.mch_id'));
    }

    public function test_apply_is_idempotent(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['settings' => ['integrations' => ['wechat' => ['app_id' => 'wx-first']]]]);

        IntegrationConfig::apply();

        // 第二次 apply 不得重新读库（否则会读到改后的 settings 覆盖掉第一次的值）
        $tenant->update(['settings' => ['integrations' => ['wechat' => ['app_id' => 'wx-second']]]]);
        IntegrationConfig::apply();

        $this->assertSame('wx-first', config('wechat.app_id'));
        $this->assertNull(IntegrationConfig::reset());
        IntegrationConfig::apply();
        $this->assertSame('wx-second', config('wechat.app_id'));
    }

    public function test_notify_route_outside_web_group_applies_config(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['settings' => ['integrations' => ['pay' => [
            'notify_url' => 'https://example.com/pay/wechat/notify',
        ]]]]);

        config()->set('pay.wechat.default.notify_url', 'https://env.example.com/pay/wechat/notify');

        // notify 在 web 组之外，全局 web 中间件到不了；此处模拟微信直推一次回调
        $this->post('/pay/wechat/notify', []);

        $this->assertSame('https://example.com/pay/wechat/notify', config('pay.wechat.default.notify_url'));
    }

    public function test_template_push_applies_config_itself(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['settings' => ['integrations' => ['wechat' => [
            'templates' => ['harvest_notice' => 'TPL-HARVEST-1'],
        ]]]]);

        config()->set('wechat.templates.harvest_notice', ''); // 模拟定时命令/队列：请求中间件没跑过

        $user = User::create([
            'tenant_id' => $tenant->id,
            'openid' => 'mock_openid_push',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
        ]);

        app(WechatTemplateService::class)->send($user, 'harvest_notice', ['data' => []]);

        $this->assertSame('TPL-HARVEST-1', config('wechat.templates.harvest_notice'));
        $this->assertDatabaseHas('push_messages', [
            'user_id' => $user->id,
            'template_id' => 'TPL-HARVEST-1',
            'type' => 'harvest_notice',
        ]);
    }

    public function test_production_ignores_mock_override_from_settings(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['settings' => ['integrations' => [
            'wechat' => ['mock' => true, 'app_id' => 'wx-stored'],
            'pay' => ['mch_id' => '1900000109'],
        ]]]);

        // 模拟生产环境：env 没配 WECHAT_MOCK，config 默认 false
        config()->set('wechat.mock', false);
        $this->app['env'] = 'production';

        IntegrationConfig::apply();

        // 生产环境的 mock 必须由 env 决定，后台值不许盖掉——
        // 否则勾错一次就静默关掉真实支付，而前台毫无报错
        $this->assertFalse(config('wechat.mock'));
        $this->assertFalse(IntegrationConfig::mockEditable());

        // 其余项不受影响，照常覆盖 env
        $this->assertSame('wx-stored', config('wechat.app_id'));
        $this->assertSame('1900000109', config('pay.wechat.default.mch_id'));
    }

    public function test_route_carries_the_config_middleware(): void
    {
        $route = Route::getRoutes()->getByName('pay.wechat.notify');

        $this->assertNotNull($route);
        $this->assertContains('App\Http\Middleware\IntegrationConfigMiddleware', $route->gatherMiddleware());
    }
}
