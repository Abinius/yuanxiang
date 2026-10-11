<?php

// 冒烟：把全部 GET 路由打一遍，抓 500 / 404（带 cookie jar 保持会话）
// 用法：php dev/smoke-routes.php  [http://127.0.0.1:8000]

require __DIR__.'/smoke-lib.php';

$base = smokeBase($argv);
$jar = tempnam(sys_get_temp_dir(), 'jar') . '.txt';
file_put_contents($jar, '');
smokeApp();

$total = 0;
$bad = [];   // 500 一律算错；公开路由 4xx 也算错；受保护路由 401/403/302 属预期
foreach (smokeRoutes() as $r) {
    if (! in_array('GET', $r['methods'], true)) continue;
    $u = smokeSubstitute($r['uri']);
    if ($u === null || isset(smokeSkipUri()[$r['uri']])) continue;
    $total++;

    [$code, ] = smokeHttp($base . $u, $jar);

    // 判定：5xx 一律算错；无参数路由的 4xx 算错（路由本身坏了）。
    // 带参数路由 404 多为「该 id 不存在」，受保护路由 401/403 是预期，均不计。
    if ($code >= 500 || (! $r['guarded'] && ! str_contains($r['uri'], '{') && $code >= 400)) {
        $bad[] = "$code  $u";
    }
}

echo "GET 路由共 $total 条\n\n异常状态码：\n";
echo empty($bad) ? "（无）\n" : implode("\n", $bad) . "\n";

@unlink($jar);
