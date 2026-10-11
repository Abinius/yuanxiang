<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * 站名是中文（光彩云村庄 / 陌上原乡）时，APP_NAME 过 Str::slug 得空串，
 * Laravel 默认的会话 cookie 名会退化成 `-session`——不是合法 RFC 6265 token，
 * 严格客户端（curl、部分浏览器/代理）根本不存 → 会话丢失 → 登录和所有 POST 一律 419。
 * config/session.php 里已兜底成 ASCII 前缀，这里守着不让它退回 `-session`。
 */
class SessionCookieNameTest extends TestCase
{
    public function test_session_cookie_name_is_a_valid_token(): void
    {
        $name = config('session.cookie');

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_][A-Za-z0-9_.\-]*$/',
            $name,
            "会话 cookie 名 `{$name}` 不是 ASCII 安全 token；中文站名会把它退化成 `-session`，严格客户端不存这个 cookie，会话丢了登录就 419。",
        );
    }
}
