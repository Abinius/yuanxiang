<?php

// 冒烟：把全部 GET 路由打一遍，抓 500 / 404（带 cookie jar 保持会话）
// 用法：php dev/smoke-routes.php  [http://127.0.0.1:8000]

$base = $argv[1] ?? 'http://127.0.0.1:8000';
$jar = tempnam(sys_get_temp_dir(), 'jar') . '.txt';
file_put_contents($jar, '');

require 'vendor/autoload.php';
require __DIR__.'/route-auth.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = [];
foreach (app('router')->getRoutes() as $route) {
    if (str_starts_with((string) $route->uri(), 'vendor/')) continue;

    // 挂 auth 的路由，匿名访问 302/403 是预期；公开路由出现 4xx/5xx 才算异常
    $guarded = routeIsGuarded($route->gatherMiddleware());

    $rows[] = [
        'uri' => $route->uri(),
        'method' => array_map('strtoupper', $route->methods()),
        'guarded' => $guarded,
    ];
}

$ids = ['plot' => 1, 'camera' => 1, 'adoption' => 1, 'adjustment' => 1, 'adjustments' => 1,
    'coupon' => 1, 'payout' => 1, 'payment' => 1, 'code' => 1, 'giftBox' => 1, 'giftbox' => 1,
    'delivery' => 1, 'promotion' => 1, 'user' => 1, 'tenant' => 1, 'farm_member' => 1,
    'short_link' => 1, 'traceCode' => 1, 'batch' => 1, 'harvest' => 1, 'farm_log' => 1,
    'log' => 1, 'contract' => 1, 'order' => 1, 'member' => 1, 'memberLevel' => 1];

$total = 0;
$bad = [];   // 500 一律算错；公开路由 4xx 也算错；受保护路由 401/403/302 属预期
foreach ($rows as $r) {
    if (!in_array('GET', array_map('strtoupper', $r['method']))) continue;
    if (str_starts_with((string) $r['uri'], 'vendor/')) continue;
    $total++;

    $u = '/' . str_replace('%257B', '{', $r['uri']);
    $hasParam = str_contains($u, '{');
    foreach ($ids as $k => $v) $u = str_replace('{' . $k . '}', (string) $v, $u);
    if (preg_match('/\{[^}]+\}/', $u)) continue;   // 参数没喂全，跳过

    $ch = curl_init($base . $u);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 判定：5xx 一律算错；无参数路由的 4xx 算错（路由本身坏了）。
    // 带参数路由 404 多为「该 id 不存在」，受保护路由 401/403 是预期，均不计。
    $broken = $code >= 500 || (! $r['guarded'] && ! $hasParam && $code >= 400);
    if ($broken) $bad[] = "$code  $u";
}

echo "GET 路由共 $total 条\n\n异常状态码：\n";
echo empty($bad) ? "（无）\n" : implode("\n", $bad) . "\n";

@unlink($jar);
