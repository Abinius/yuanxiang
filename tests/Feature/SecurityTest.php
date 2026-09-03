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

    /** CSRF 保护:应用在 bootstrap/app.php 配置了 validateCsrfTokens（Laravel 12 薄内核下
     *  getMiddlewareGroups() 不再暴露该中间件类，改验证配置声明这一安全属性）。 */
    public function test_csrf_protection_is_enabled(): void
    {
        $source = (string) file_get_contents(base_path('bootstrap/app.php'));
        $this->assertMatchesRegularExpression(
            "#->validateCsrfTokens\s*\(#",
            $source,
            'bootstrap/app.php 应配置 validateCsrfTokens()'
        );
        // 不应把所有路由都排除在 CSRF 之外（except 只放行微信支付回调等白名单）
        $this->assertMatchesRegularExpression(
            "#validateCsrfTokens\s*\(\s*except:\s*\[[^\]]+\]#",
            $source,
            'CSRF except 应为受限白名单，而非全放行'
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

    /** WECHAT_MOCK env 回落默认值须为 false,防生产静默 mock。
     *  测试环境显式设 WECHAT_MOCK=true 走 mock 通道,故验证定义层回落默认。 */
    public function test_wechat_mock_defaults_to_false(): void
    {
        $source = (string) file_get_contents(config_path('wechat.php'));
        $this->assertMatchesRegularExpression(
            "#'mock'\s*=>\s*env\(\s*['\"]WECHAT_MOCK['\"]\s*,\s*false\s*\)#",
            $source,
            'wechat.mock 的 env 回落默认须为 false'
        );
    }

    /** 金额字段应为 decimal 精度,非整数截断。
     *  SQLite 的 getColumnType 返回 'numeric'（DECIMAL 亲和），故断言：
     *  1) 迁移声明 decimal(10,2)（生产 MySQL 即真精度）；
     *  2) 实际存取保留小数（插入 5000.55 → 读出仍是 5000.55，非截断为 5000）。 */
    public function test_annual_fee_is_decimal_precision(): void
    {
        // 1) schema 声明层：迁移使用 decimal 而非 integer
        $migration = file_get_contents(database_path('migrations/2026_09_01_000002_change_annual_fee_to_decimal.php'));
        $this->assertMatchesRegularExpression(
            "#decimal\('annual_fee',\s*10,\s*2\)#",
            $migration,
            'annual_fee 迁移应声明 decimal(10,2)'
        );

        // 2) 功能层：SQLite 存储保持小数精度
        $this->seed([\Database\Seeders\BaseSeeder::class, \Database\Seeders\PlotSeeder::class]);
        $tenant = \App\Models\Tenant::where('slug', 'guangcai')->firstOrFail();
        $user = \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'phone' => '13800000091',
            'password' => 'secret123',
            'nickname' => '精度测试',
            'role' => 'villager',
        ]);
        $plot = \App\Models\Plot::where('tenant_id', $tenant->id)->firstOrFail();
        $adoption = \App\Models\Adoption::create([
            'tenant_id' => $tenant->id,
            'adoption_no' => 'AD-DEC-001',
            'user_id' => $user->id,
            'adoptable_type' => \App\Models\Plot::class,
            'adoptable_id' => $plot->id,
            'farm_id' => $plot->farm_id,
            'annual_fee' => 5000.55,
            'start_date' => '2026-01-01',
            'season_year' => 2026,
            'status' => 'pending_payment',
        ]);
        $stored = \App\Models\Adoption::find($adoption->id)->annual_fee;
        $this->assertSame(5000.55, (float) $stored, 'decimal 字段存取应保留小数精度，不截断');
    }
}
