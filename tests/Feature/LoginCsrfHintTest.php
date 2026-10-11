<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 登录表单停留过久后提交（会话过期、表单 token 失效）用户看到的是英文 419 页。
 * bootstrap/app.php 把这两个登录端点的 419 换成中文提示 + 新表单。
 *
 * 异常到回调前已被 prepareException() 转成通用 HttpException(419)，测试也喂同一个东西。
 * VerifyCsrfToken 在测试里整体跳过校验（runningUnitTests），触发不了真实异常。
 */
class LoginCsrfHintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 渲染异常时中间件会取唯一租户
        Tenant::create(['slug' => 'guangcai', 'name' => '光彩云村庄', 'status' => 'active']);
    }

    /** 造一个带指定路由名的 POST 请求，把 419 喂给异常处理器。 */
    private function render419(string $routeName)
    {
        $request = Request::create('/login', 'POST');
        $route = new Route('POST', '/login', function () {});
        $route->name($routeName);
        $request->setRouteResolver(fn () => $route);

        return app(ExceptionHandler::class)->render($request, new HttpException(419, 'CSRF token mismatch.'));
    }

    public function test_login_419_becomes_redirect_with_chinese_hint(): void
    {
        $response = $this->render419('tenant.login.post');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringEndsWith(route('tenant.login', absolute: false), $response->getTargetUrl());
    }

    public function test_platform_login_419_goes_back_to_platform_login(): void
    {
        $response = $this->render419('platform.login.post');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringEndsWith(route('platform.login', absolute: false), $response->getTargetUrl());
    }

    public function test_419_outside_login_routes_is_untouched(): void
    {
        $response = $this->render419('tenant.logout');

        $this->assertSame(419, $response->getStatusCode());
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
    }

    public function test_other_status_codes_are_untouched(): void
    {
        $request = Request::create('/login', 'POST');
        $route = new Route('POST', '/login', function () {});
        $route->name('tenant.login.post');
        $request->setRouteResolver(fn () => $route);

        $response = app(ExceptionHandler::class)->render($request, new HttpException(404, 'not found'));

        $this->assertSame(404, $response->getStatusCode());
    }
}
