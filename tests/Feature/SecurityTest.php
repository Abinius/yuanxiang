<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 安全基线冒烟测试：CSRF 开启、登录限流、支付 mock 默认关闭、短链协议白名单。
 * 这些都是"配置/声明层面"的可验证安全属性，无需真实 HTTP 攻击即可断言。
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** CSRF 保护:VerifyCsrfToken 在 web 中间件组中注册。 */
    public function test_csrf_protection_is_enabled(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        // Laravel 12:VerifyCsrfToken 在 $middlewareGroups['web'] 中
        $webMiddleware = $kernel->getMiddlewareGroups()['web'] ?? [];
        $this->assertTrue(
            in_array(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, $webMiddleware, true),
            'VerifyCsrfToken 应在 web 中间件组中'
        );
    }

    /** 登录路由必须挂 throttle 中间件,防暴力破解。 */
    public function test_login_routes_have_throttle(): void
    {
        Artisan::call('route:clear');
        $postLogin = Route::getRoutes()->getByName('tenant.login.post');
        $this->assertNotNull($postLogin, 'tenant.login.post 路由应存在');

        $middleware = $postLogin->middleware();
        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle')),
            '登录 POST 路由应挂 throttle 中间件'
        );
    }

    /** 平台登录同样应限速。 */
    public function test_platform_login_has_throttle(): void
    {
        $route = Route::getRoutes()->getByName('platform.login.post');
        $this->assertNotNull($route);
        $this->assertTrue(
            collect($route->middleware())->contains(fn ($m) => str_contains((string) $m, 'throttle'))
        );
    }

    /** 微信扫码/短链/扫码开放路由应限速。 */
    public function test_open_routes_have_throttle(): void
    {
        $scan = Route::getRoutes()->getByName('tenant.scan.show');
        $shortLink = Route::getRoutes()->getByName('tenant.short-link.redirect');

        $this->assertTrue(
            collect($scan->middleware())->contains(fn ($m) => str_contains((string) $m, 'throttle')),
            '扫码页应限速'
        );
        $this->assertTrue(
            collect($shortLink->middleware())->contains(fn ($m) => str_contains((string) $m, 'throttle')),
            '短链跳转应限速'
        );
    }

    /** WECHAT_MOCK 默认值必须为 false,防生产静默 mock。 */
    public function test_wechat_mock_defaults_to_false(): void
    {
        $this->assertFalse(config('wechat.mock'));
    }

    /** 金额字段应为 decimal 精度,非整数截断。 */
    public function test_annual_fee_is_decimal_precision(): void
    {
        $schema = Schema::getColumnType('adoptions', 'annual_fee');
        $this->assertStringContainsString('decimal', strtolower((string) $schema));
    }
}
