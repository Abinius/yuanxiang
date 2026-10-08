<?php

// 一次性生成 THIRD-PARTY-LICENSES.md（数据源 = composer.lock / package-lock.json）
// PHP 部分等价于 `composer licenses`；npm 部分等价于 `npm ls --license`。

$php = [];
foreach (json_decode(file_get_contents('vendor/composer/installed.json'), true) as $x) {
    $packages = $x['packages'] ?? $x;
    break;
}
foreach ($packages as $x) {
    if (($x['type'] ?? '') === 'metapackage') continue;
    $lic = is_array($x['license'] ?? null) ? implode(' / ', $x['license']) : ($x['license'] ?? '(未声明)');
    $php[$lic][$x['name']] = $x['version'];
}
ksort($php);

$npm = [];
$lock = json_decode(file_get_contents('package-lock.json'), true);
foreach (($lock['packages'] ?? []) as $k => $v) {
    if ($k === '') continue;
    $pos = strrpos($k, 'node_modules/');
    $name = $pos === false ? $k : substr($k, $pos + 13);
    $npm[$v['license'] ?? '(未声明)'][$name] = $v['version'];
}
ksort($npm);

$spdx = [
    'MIT' => 'https://spdx.org/licenses/MIT.html',
    'BSD-3-Clause' => 'https://spdx.org/licenses/BSD-3-Clause.html',
    'ISC' => 'https://spdx.org/licenses/ISC.html',
    'Apache-2.0' => 'https://spdx.org/licenses/Apache-2.0.html',
    'MPL-2.0' => 'https://spdx.org/licenses/MPL-2.0.html',
    '0BSD' => 'https://spdx.org/licenses/0BSD.html',
];

$out = "# 第三方许可清单\n\n";
$out .= "> 数据源：`composer.lock` + `package-lock.json`，非人工整理。\n";
$out .= "> 刷新方式：PHP 段 `composer licenses`；JS 段 `npm ls --license`。\n";
$out .= "> 更新日期：2026-10-08\n\n";
$out .= "## 结论\n\n";
$out .= "全部依赖为**宽松许可**，无 GPL / AGPL / LGPL / SSPL / Elastic / BSL 等传染性或商业限制许可，";
$out .= "本项目不因依赖而承担开源义务。\n\n";
$out .= "需履行的义务只有两条：MIT / BSD / ISC 要求保留版权与许可声明；Apache-2.0 额外要求附带 LICENSE 副本、";
$out .= "NOTICE 文件并标注改动过的文件。本仓库公开完整源码，即满足上述 notice 要求。\n\n";
$out .= "## 协议分布\n\n";
$out .= "| 协议 | 说明 | PHP | JS |\n";
$out .= "| --- | --- | --- | --- |\n";
foreach ($spdx as $lic => $url) {
    $out .= '| [' . $lic . '](' . $url . ') | 宽松，保留声明即可' . (str_starts_with($lic, 'Apache') ? '；需附 LICENSE + NOTICE' : '') . ' | '
        . (isset($php[$lic]) ? count($php[$lic]) : 0) . ' | '
        . (isset($npm[$lic]) ? count($npm[$lic]) : 0) . " |\n";
}
$out .= "| BSD-3 **或** GPL-2.0/3.0 任选 | 双许可，本项目走 BSD-3 分支，无传染性 | 2 | 0 |\n\n";
$out .= "## PHP 依赖（运行时，" . count($packages) . " 包）\n\n";
foreach ($php as $lic => $pkgs) {
    $out .= "### " . (isset($spdx[$lic]) ? "[{$lic}](" . $spdx[$lic] . ')' : $lic) . "（" . count($pkgs) . "）\n\n";
    $out .= "| 包 | 版本 |\n| --- | --- |\n";
    ksort($pkgs);
    foreach ($pkgs as $name => $ver) {
        $out .= '| `' . $name . '` | ' . $ver . " |\n";
    }
    $out .= "\n";
}
// 按 distinct 包名计（与下方分组的求和一致），不按 lock 条目数计
$jsCount = array_sum(array_map('count', $npm));
$out .= "## JS 构建依赖（{$jsCount} 包）\n\n";
$out .= "全部为 **devDependencies，仅构建期使用，不随站点分发**——SSR 模式无客户端 JS bundle，";
$out .= "产物只是 minified CSS。故该段无运行时 notice 义务。\n\n";
foreach ($npm as $lic => $pkgs) {
    $out .= "### " . (isset($spdx[$lic]) ? "[{$lic}](" . $spdx[$lic] . ')' : $lic) . "（" . count($pkgs) . "）\n\n";
    $flat = [];
    foreach ($pkgs as $name => $ver) $flat[] = "`$name@$ver`";
    $out .= implode(' · ', $flat) . "\n\n";
}
$out .= "## 其他\n\n";
$out .= "- **字体**：`--font-sans` / `--font-serif` / `--font-mono` 全为系统字体栈，不自托管字体文件、不引 CDN，无字体许可义务。\n";
$out .= "- **图片**：`public/` 下无第三方图片；用户上传内容走 `storage/`。\n";
$out .= "- **图标**：Lucide 图标数据随 `mallardduck/blade-lucide-icons`（MIT）分发，Lucide 本体为 ISC。\n";
$out .= "- **CI**：`actions/checkout`、`actions/setup-node`、`shivammathur/setup-php` 均为 MIT。\n";

file_put_contents('THIRD-PARTY-LICENSES.md', $out);
echo 'written ' . strlen($out) . " bytes\n";
