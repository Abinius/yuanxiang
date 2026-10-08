<?php

// 从实际路由生成权限矩阵（代码即真源，避免手写漂移）。
// 用法：php dev/gen-permission-matrix.php

require 'vendor/autoload.php';
require __DIR__.'/route-auth.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$router = app('router');
$rows = [];
foreach ($router->getRoutes() as $route) {
    $name = $route->getName();
    $uri = $route->uri();

    if (str_starts_with((string) $uri, 'vendor/')) {
        continue;
    }

    // 按路由组前缀归域
    $domain = 'C 端公开';
    if (str_starts_with($uri, 'platform/')) {
        $domain = '平台后台';
    } elseif (str_starts_with($uri, 'admin')) {
        $domain = '商户后台';
    } elseif (str_starts_with($uri, 'family')) {
        $domain = '家人端';
    } elseif (str_starts_with($uri, 'my') || str_starts_with($uri, 'live')) {
        $domain = '云乡民（我的田／云监看）';
    }

    $auth = routeAuthLabel($route->gatherMiddleware());

    // action 归属控制器类名（简短显示）；闭包路由标记为 [闭包]
    $action = $route->getActionName();
    $handler = str_contains($action, '@') ? basename(ltrim(explode('@', $action)[0], '\\')) . '::' . explode('@', $action)[1] : '[闭包]';

    $rows[] = [$domain, $uri, $auth, $handler, $name];
}

// 归域 + 计数
$summary = [];
foreach ($rows as $r) {
    $summary[$r[0]] ??= ['total' => 0, 'open' => 0, 'login' => 0, 'role' => 0];
    $summary[$r[0]]['total']++;
    if ($r[2] === '公开') {
        $summary[$r[0]]['open']++;
    } elseif (str_starts_with($r[2], '登录 + role')) {
        $summary[$r[0]]['role']++;
    } else {
        $summary[$r[0]]['login']++;
    }
}

$out = "# 陌上原乡 · 权限矩阵\n\n";
$out .= "> 产出物（原 PRD v2.0 第 3 篇承诺未给，见复工计划 §6 #13）\n";
$out .= "> 生成日期：2026-10-08　|　**代码即真源**：本表由 `routes/web.php` + `routes/platform.php` 推导。\n";
$out .= "> 重生方式：`php dev/gen-permission-matrix.php`（枚举规则改动后请同步跑一次）。\n\n";

$out .= "## 角色\n\n";
$out .= "| 角色 | 值 | 说明 |\n| --- | --- | --- |\n";
$out .= "| 游客 | — | 未登录，仅公开页 |\n";
$out .= "| 云乡民 | `villager` | C 端主账号：下单、支付、签约、我的田、云监看、礼盒、续费、推荐佣金 |\n";
$out .= "| 家人 | `family` | 家人端录入农事/采收，按 `farm_members.permission_scope` 细分录入权限 |\n";
$out .= "| 商户管理员 | `tenant_admin` | 商户后台全部；家人端入口也放行（同 `family` 组） |\n";
$out .= "| 平台管理员 | `platform_admin` | 平台后台（跨租户建号、租户启停），走独立登录与路由前缀 |\n\n";

$out .= "## 分域汇总\n\n";
$out .= "| 域 | 路由数 | 公开 | 仅登录 | 登录 + 角色 |\n| --- | --- | --- | --- | --- |\n";
foreach ($summary as $d => $c) {
    $out .= "| {$d} | {$c['total']} | {$c['open']} | {$c['login']} | {$c['role']} |\n";
}
$out .= "| **合计** | " . count($rows) . " | "
    . array_sum(array_column($summary, 'open')) . " | "
    . array_sum(array_column($summary, 'login')) . " | "
    . array_sum(array_column($summary, 'role')) . " |\n\n";

$out .= "## 角色可见性矩阵\n\n";
$out .= "| 域 | 游客 | 云乡民 | 家人 | 商户管理员 | 平台管理员 |\n";
$out .= "| --- | --- | --- | --- | --- | --- |\n";
$out .= "| C 端公开页 | ✅ | ✅ | ✅ | ✅ | ✅ |\n";
$out .= "| 我的田／云监看（仅登录） | — | ✅ | ✅ | ✅ | ✅ |\n";
$out .= "| 家人端（`role:family,tenant_admin`） | — | — | ✅ | ✅ | — |\n";
$out .= "| 商户后台（`role:tenant_admin`） | — | — | — | ✅ | — |\n";
$out .= "| 平台后台（`role:platform_admin`） | — | — | — | — | ✅ |\n\n";

$out .= "## 关键鉴权设计\n\n";
$out .= "1. **公开页刻意不挂 auth**：首页、认养浏览、溯源时间线、溯源码扫码页、\n";
$out .= "   礼盒收礼人落地页、短链跳转、公开铭牌——都是**转化入口**，登录墙会打断拉新。\n";
$out .= "2. **云乡民资源路由统一挂 `auth` + `tenant.member`**：单租户下 `tenant.member`\n";
$out .= "   已退化为透传（保留别名以免大改路由），实际鉴权靠 `auth` + 控制器内的\n";
$out .= "   owner-gated 校验（`\ !== \()->id` → 404）。\n";
$out .= "3. **家人端录权限是二级**：`role:family,tenant_admin` 只放行进门，\n";
$out .= "   具体能否录某类农事由 `FarmMember.permission_scope` 数组判定\n";
$out .= "   （`farm_log` / `fertilizer` / `harvest`），`tenant_admin` 直通全部。\n";
$out .= "4. **商户后台不能动平台管理员**：`Admin/UserController` 的 role 白名单只含\n";
$out .= "   家人/云乡民/商户管理员，`platform_admin` 403；也不能禁用自己（422）。\n";
$out .= "5. **平台后台独立路由前缀 `/platform`**：guest 重定向按 `platform/*` 分流\n";
$out .= "   到平台登录页，其余到租户登录页。\n";
$out .= "6. **监控不公开**：`/live` 挂 `auth`——摄像头涉家人肖像隐私，不开放给游客。\n";

file_put_contents('D:/Abin/原乡/陌上原乡 · 权限矩阵.md', $out);
echo 'written ' . strlen($out) . " bytes\n";
