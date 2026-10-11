<?php

// 写路径冒烟：以 4 个身份登录，把登录后能看到的每个页面渲染一遍，再把这些页面上的表单原样提交一遍，
// 抓 500（代码 bug）与 404（表单指向不存在的路由——点按钮点了个寂寞）。
// 用法：php dev/smoke-write.php  [http://127.0.0.1:8000]  [身份关键词]
//   第二个参数可只跑某个身份（如 "家人"），配合重新 seed 逐个跑最干净。
// 前置：php artisan migrate:fresh --seed（账号 admin / platform / 13900000001 / 13800000001，密码均 test1234）。
// 会真实写库：建批次、建采收、发货，还会把后台「禁用账号」按钮点一遍——
// 每个身份登录前会把 users.is_disabled 复位成 0，否则前面角色的冒烟会让后面角色登不上。
// 跑完建议重新 migrate:fresh --seed 复位（脚本本身不做回滚）。
// /login 有 throttle:5,1，本脚本要登录 4 次（租户登录桶 3 次 + 平台登录桶 1 次），一个周期内只跑一次。

require __DIR__.'/smoke-lib.php';

$base = smokeBase($argv);
smokeApp();

/** 单个标签的属性取值 */
function attr(string $tag, string $name): ?string
{
    return preg_match('/\b' . $name . '\s*=\s*"([^"]*)"/', $tag, $m) ? $m[1] : null;
}

/** 全部匹配项，无匹配时返回空数组 */
function all(string $pattern, string $subject): array
{
    return preg_match_all($pattern, $subject, $m) ? $m[0] : [];
}

/** 页面里的表单：action / method / 字段（含 _token、_method 隐藏字段） */
function parseForms(string $html): array
{
    $forms = [];
    preg_match_all('/<form\b[^>]*>/', $html, $tags, PREG_OFFSET_CAPTURE);

    foreach ($tags[0] as $hit) {
        $start = $hit[1];
        $end = strpos($html, '</form>', $start);
        $chunk = substr($html, $start, $end === false ? null : $end - $start);
        $open = preg_match('/<form\b[^>]*>/', $chunk, $m) ? $m[0] : $chunk;

        $method = strtoupper(attr($open, 'method') ?? 'post');
        $action = trim((string) (attr($open, 'action') ?? ''));

        $fields = [];
        foreach (all('/<input\b[^>]*>/i', $chunk) as $tag) {
            $name = attr($tag, 'name');
            $type = strtolower((string) (attr($tag, 'type') ?? 'text'));
            if ($name === null || in_array($type, ['file', 'submit', 'button', 'reset', 'image'], true)) continue;
            if ($type === 'checkbox' || $type === 'radio') {
                // 未勾选不提交：Laravel boolean() 对缺省字段返回 false，与勾选框语义一致
                if (preg_match('/\bchecked\b/i', $tag)) $fields[$name] = attr($tag, 'value') ?? '1';
                continue;
            }
            $fields[$name] = attr($tag, 'value') ?? '';
        }
        // select 取已选项，否则第一个可选项
        foreach (all('/<select\b[^>]*>.*?<\/select>/is', $chunk) as $tag) {
            $name = attr($tag, 'name');
            if ($name === null) continue;
            $options = all('/<option\b[^>]*>/i', $tag);
            foreach ($options as $opt) {
                $value = attr($opt, 'value');
                if ($value === null) continue;
                if (!preg_match('/\bselected\b/i', $opt) && ! empty($fields[$name])) continue;
                $fields[$name] = $value;
                break;
            }
        }

        // HTML 表单用隐藏 _method 做方法伪造，以它为准；随后靠 POST 提交，由框架还原真实方法
        $method = strtoupper($fields['_method'] ?? $method);
        $fields['_method'] ??= '';

        // DELETE 会真的删数据，跳过；GET 表单是筛选不是写入
        if (in_array($method, ['DELETE', 'GET'], true)) continue;

        $forms[] = [
            'action' => $action,
            'method' => $method,
            'fields' => $fields,
        ];
    }

    return $forms;
}

/** @return array<string, array{login:string, account:string}> */
function roles(): array
{
    return [
        '商户后台(租户管理员)' => ['login' => '/login', 'account' => 'admin'],
        '家人录入端(家人)' => ['login' => '/login', 'account' => '13900000001'],
        'C 端(云乡民)' => ['login' => '/login', 'account' => '13800000001'],
        '平台后台(平台管理员)' => ['login' => '/platform/login', 'account' => 'platform'],
    ];
}

$summary = [];
$fail = [];

$only = $argv[2] ?? '';

foreach (roles() as $label => $role) {
    if ($only !== '' && ! str_contains($label, $only)) continue;

    // 冒烟会把后台账号禁用状态点来点去，登录前先复位，否则后面身份登不上
    DB::table('users')->where('is_disabled', 1)->update(['is_disabled' => 0]);

    $jar = tempnam(sys_get_temp_dir(), 'ws') . '.txt';
    file_put_contents($jar, '');
    $loginCode = smokeLogin($base, $jar, $role['login'], $role['account'], 'test1234');
    if ($loginCode !== 302) {
        echo "⚠️  {$label} 登录失败：HTTP $loginCode" . ($loginCode === 429 ? '（/login 限流 5 次/分钟，等一个周期再跑）' : '') . "\n";
        @unlink($jar);
        continue;
    }
    echo "    {$label} 登录 302；jar 里 "
        . (preg_match_all('/\t([A-Za-z0-9_-]+)\t/', file_get_contents($jar), $names) > 0 ? 'cookie=' . implode(',', array_unique($names[1])) : '没有 cookie') . "\n";

    $pages = $rendered = $expected = 0;
    $forms = $ok = $xx4 = $throttled = 0;

    foreach (smokeRoutes() as $r) {
        if (! in_array('GET', $r['methods'], true)) continue;
        $u = smokeSubstitute($r['uri']);
        if ($u === null || isset(smokeSkipUri()[$r['uri']])) continue;
        $pages++;

        [$code, $body, $loc] = smokeHttp($base . $u, $jar);
        if ($code === 200) {
            $rendered++;
        } elseif ($code >= 500 || ($code !== 403 && ! str_contains($r['uri'], '{'))) {
            // 403 是越权拦截（预期）；带 {参数} 的路由 404 多为「该 id 不存在」，也都属预期。
            // 5xx 与无参路由的其他 4xx 才算异常。
            // 302 要看跳去哪：跳 /login 是没登录（会话没留住），跳别处是控制器主动跳转
            $to = $loc !== '' ? ' → ' . $loc : '';
            $fail[] = "    渲染 $code  GET $u$to";
        } else {
            $expected++;
        }
        if ($code !== 200) continue;   // 没渲染出来就没有表单可提

        foreach (parseForms($body) as $f) {
            $target = $f['action'] !== '' ? $f['action'] : $u;

            // 退出登录表单：一提交整个会话就没了，后面全是游客（退出本身由 AuthTest 覆盖）
            if (preg_match('#/logout\b#', $target)) continue;

            $forms++;
            $url = $f['action'] === ''
                ? $base . $u
                : (str_starts_with($f['action'], 'http') ? $f['action'] : $base . '/' . ltrim($f['action'], '/'));

            $fields = $f['fields'];
            $token = smokeCsrfToken($body);
            if ($token !== null) $fields['_token'] = $token;
            if ($fields['_method'] === '') unset($fields['_method']);

            [$code, ] = smokeHttp($url, $jar, $fields);
            if ($code >= 500) {
                $fail[] = "    500  {$f['method']} $target  ← 页面 GET $u";
            } elseif ($code === 404) {
                $fail[] = "    404  {$f['method']} $target  ← 页面 GET $u（表单指向不存在的路由）";
            } elseif ($code === 429) {
                $throttled++;
            } elseif ($code >= 400) {
                $xx4++;
            } else {
                $ok++;
            }
        }
    }

    $summary[] = [$label, $rendered, $pages, $expected, $forms, $ok, $xx4, $throttled];
    @unlink($jar);
}

echo "登录后页面渲染 / 表单提交（4xx 多为主键校验拦截，属预期；429 为限流）\n\n";
printf("%-22s %6s %6s %6s %6s %6s %6s %5s\n", '身份', '渲染', '页面', '预期', '表单', '成功', '4xx', '429');
foreach ($summary as [$label, $rendered, $pages, $expected, $forms, $ok, $xx4, $throttled]) {
    printf("%-22s %6d %6d %6d %6d %6d %6d %5d\n", $label, $rendered, $pages, $expected, $forms, $ok, $xx4, $throttled);
}

echo "\n异常（渲染 500 = 代码 bug；渲染 4xx = 路由本身坏了；表单 404 = 表单指向死路由）：\n";
echo empty($fail) ? "（无）\n" : implode("\n", array_unique($fail)) . "\n";
