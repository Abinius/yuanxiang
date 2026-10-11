<?php

// 冒烟脚本共用件：路由枚举、路由参数替换、带 cookie 的 HTTP、账号登录。
// smoke-routes.php（匿名 GET）与 smoke-write.php（登录后填表提交）都用它。

require __DIR__.'/route-auth.php';

/** @return string 目标站点，用法 php dev/smoke-*.php [http://127.0.0.1:8000] */
function smokeBase(array $argv): string
{
    return $argv[1] ?? 'http://127.0.0.1:8000';
}

function smokeApp(): void
{
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

/** @return array<int, array{uri:string, methods:string[], guarded:bool}> */
function smokeRoutes(): array
{
    $rows = [];
    foreach (app('router')->getRoutes() as $route) {
        $uri = (string) $route->uri();
        if (str_starts_with($uri, 'vendor/')) continue;
        $rows[] = [
            'uri' => $uri,
            'methods' => array_map('strtoupper', $route->methods()),
            'guarded' => routeIsGuarded($route->gatherMiddleware()),
        ];
    }

    return $rows;
}

/** 路由参数名 → 种子库里真实存在的行 id */
function smokeIdMap(): array
{
    return [
        'plot' => 1, 'camera' => 1, 'adoption' => 1, 'adjustment' => 1, 'adjustments' => 1,
        'coupon' => 1, 'payout' => 1, 'payment' => 1, 'code' => 1, 'giftBox' => 1, 'giftbox' => 1,
        'delivery' => 1, 'promotion' => 1, 'user' => 1, 'tenant' => 1, 'farm_member' => 1,
        'short_link' => 1, 'traceCode' => 1, 'batch' => 1, 'harvest' => 1, 'farm_log' => 1,
        'log' => 1, 'contract' => 1, 'order' => 1, 'member' => 1, 'memberLevel' => 1,
    ];
}

/** @return string|null 参数没喂全时返回 null（调用方跳过） */
function smokeSubstitute(string $uri): ?string
{
    $u = '/' . str_replace('%257B', '{', $uri);
    foreach (smokeIdMap() as $k => $v) {
        $u = str_replace('{' . $k . '}', (string) $v, $u);
    }

    return preg_match('/\{[^}]+\}/', $u) ? null : $u;
}

/**
 * fields 为 null 时发 GET，否则发 POST（HTML 表单口径：含 _token / _method 隐藏字段，由框架做方法伪造）。
 *
 * @return array{0:int,1:string,2:string} [状态码, 响应体, Location 头]
 */
function smokeHttp(string $url, string $jar, ?array $fields = null, int $timeout = 20): array
{
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => $timeout,
    ];
    if ($fields !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr((string) $resp, 0, $hsize);
    curl_close($ch);

    if ($resp === false) return [$code, '', ''];

    preg_match('/^Location:\s*(.+)$/mi', $headers, $loc);

    return [$code, substr($resp, $hsize), $loc[1] ?? ''];
}

/**
 * 从登录页取 CSRF token 后提交账号密码。throttle:5,1，一次跑别重复登录。
 * 返回 [状态码, 跳转目标]。登录失败也是 302（带校验错误跳回表单），
 * 所以判断成败要看跳转目标，不能只看状态码。
 *
 * @return array{0:int,1:string}
 */
function smokeLogin(string $base, string $jar, string $loginPath, string $account, string $password): array
{
    [$code, $body] = smokeHttp($base . $loginPath, $jar);
    if ($code !== 200 || ! preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)) return [-1, ''];

    [$code, , $loc] = smokeHttp($base . $loginPath, $jar, [
        '_token' => $m[1],
        'account' => $account,
        'password' => $password,
        'agreed' => '1',
    ]);

    return [$code, $loc];
}

/**
 * 已知的设计内非 2xx：冒烟里显式跳过，免得每轮都被人当新 bug 追一遍。
 *
 * @return array<string,string> uri => 为什么不算异常
 */
function smokeSkipUri(): array
{
    return [
        // 匿名访问拿不到 state，故意拒绝；带 state 的回调由 WeChatPayTest / AuthTest 覆盖
        'login/wechat/callback' => '设计内：state 校验拒绝（419）',
        // mock 模式下一 GET 就真实建号（users + 新人券），冒烟不该有副作用
        'login/wechat' => 'mock 模式会真的建号，跳过',
    ];
}

/** 从页面抽取 name="_token" 的值，表单提交靠它过 CSRF。 */
function smokeCsrfToken(string $html): ?string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
}
