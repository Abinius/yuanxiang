# 陌上原乡 · yuanxiang

[![CI](https://github.com/Abinius/yuanxiang/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Abinius/yuanxiang/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.68-EF4135)](https://laravel.com/)
[![Tailwind](https://img.shields.io/badge/Tailwind-v4-38BDF8)](https://tailwindcss.com/)
[![Database](https://img.shields.io/badge/SQLite-:memory%3A_test-003B57)](https://www.sqlite.org/)

> 宁夏红寺堡枸杞认养平台 —— 云乡民在线认养一畦田，生态种植全程可溯源。

陌上原乡（内部代号「云乡」）是一套面向认养农业的平台，以宁夏红寺堡枸杞为首个样板：城市用户（云乡民）在线认养一畦枸杞田，全程溯源、实时监控、定期配送、节日礼盒；家人端录入农事，后台统一经营。全链条内化（种植家庭 + 有机肥厂 + 在地劳力 + 品牌 + 平台）是核心差异化。

## 技术栈

- **Laravel 12.68**（PHP 8.2）+ **Blade** + **Tailwind v4**（Vite 构建，`@vite` 注入，`@theme` 语义色）—— 纯 Blade + 内联 JS，无 React/Vue
- **SQLite** 默认（可换 MySQL/PG）；**yansongda/laravel-pay**（微信支付 v3）
- **mallardduck/blade-lucide-icons** —— `<x-lucide-*>` 图标组件
- 设计系统：编辑风农业版式令牌（苔绿 `#3F6B4F` / 稻黄 `#A8791E` / 纸白底 / hairline 描边 / 直角化圆角 2·4·6·8px）；`--ds-*` token 系列
- 字体：Instrument Serif + Noto Serif SC（标题）/ Instrument Sans + Noto Sans SC（正文）/ SF Mono（代码）

## 架构

**单租户**：MVP 只服务自家 6 亩样板田，不做多租户。`tenants` 表保留作平台配置表（站点设置、定价、营销、佣金、合同条款两层 token：`config/site.php` 默认 + `tenants.settings` 覆盖），`tenant_id` 列保留。

`role` 中间件分端，无租户上下文注入：

| 端 | 路由前缀 | 角色 | 能力 |
|---|---|---|---|
| 前台 site | `/` | 公开 + 认养人 | 认养下单/签约/支付、溯源时间线、溯源码扫码、礼盒收礼、我的田（生长日历/农事动态/续费/收货/会员）、云监看、短链接、铭牌分享 |
| 后台 admin | `/admin` | `tenant_admin` | 经营看板、认养订单、农事内容、摄像头、溯源码、配送、补退、礼盒、促销、账号、佣金流水、站点设置、短链接 |
| 家人端 family | `/family` | `family` / `tenant_admin` | 地块管理、农事录入、肥料批次、采收录入（按 `FarmMember.permission_scope` 二级限权） |
| 平台后台 platform | `/platform` | `platform_admin` | 独立登录与路由前缀，跨租户建号、租户启停 |

132 条路由逐条鉴权清单见《权限矩阵》，可用 `php dev/gen-permission-matrix.php` 从路由重生。

## 状态机

4 个核心实体用**有值枚举 + `transitions()` 转移图**声明规则，不引入 spatie 包：

| 实体 | 状态链 | 入口 → 终态 |
|---|---|---|
| 认养单 | 待支付 → 待签约 → 生效中 → 已到期 | 支付后建单不原地改状态，续费的下一季是**新单** |
| 支付单 | 待支付 → 已支付 → 已退款 | 回调幂等 |
| 配送单 | 待发货 → 已发货 → 已签收 | 已发货不可重复发货 |
| 节日礼盒 | 草稿 → 制作中 → 已发货 → 已送达 | 草稿可直发 |

校验与落库统一走 `App\Models\Concerns\HasStatusTransitions`：`transitionTo()` 校验 + 落库（非法转移 422），`canTransitionTo()` 只判断不抛异常。改转移改 enum，不要改调用点。转移图见《状态机图》。

## 领域模型

27 个模型 · 11 个枚举 · 39 个控制器

Tenant · User · Plot · Farm · FarmMember · FarmLog · FertilizerBatch · Harvest · Adoption · Contract · Payment · Delivery · TraceCode · GiftBox · Promotion · Coupon · CouponUsage · ShortLink · Payout · Plan · Organization · Address · DetectionReport · PushMessage · AdoptionAdjustment · CommissionLedger · Camera

## 本地开发

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build       # 或 npm run dev 热更新
php artisan serve                  # 127.0.0.1:8000
```

一键并发起服务（composer script）：

```bash
composer dev    # serve + queue:listen + pail + vite 并发
```

生产队列与定时任务：`commission:settle` 06:30、`member:recalculate` 06:40、`member:birthday-benefit` 月初、续费提醒（30/7/1 天）。

## 测试

```bash
composer test      # = php artisan config:clear && php artisan test
```

258 例全绿（902 断言），SQLite `:memory:`，`WECHAT_MOCK=true`。CI 见 `.github/workflows/ci.yml`。

## 设计系统

- `resources/css/app.css` —— `@theme` 语义色 + 组件 class 库（导航/卡片/按钮/表格/标签/空态/分页/表单），`--ds-*` token 系列
- `resources/views/components/design-system/` —— 共享 partials
- 两个 layout：`layouts/site.blade.php`（前台）+ `layouts/dashboard.blade.php`（admin/family）
- 后台可配品牌色经 `tenant.settings.brand` 注入 CSS 变量覆盖静态令牌

## 目录结构

```
app/Http/Controllers/
  Site/      前台（认养/溯源/我的田/直播/礼盒/短链/分享）
  Admin/     商户后台
  Family/    家人端录入
  Platform/  平台后台
  Auth/      双登录（账密 + 微信）
  Pay/       微信支付
app/Enums/   状态枚举 + transitions() 转移图
app/Models/  领域模型（TenantScoped 仅自动填充 tenant_id）
app/Services/ AdoptionService · DeliveryService · GiftBoxService · CommissionService · MemberService
resources/views/{site,admin,family,components,layouts}
routes/{web.php, platform.php}
```

## 背景

宁夏红寺堡光彩村枸杞种植家庭出身，全链条内化：种植家庭 + 有机肥厂（NXLB）+ 在地农业劳力 + 品牌 + 平台。6 亩枸杞「云乡民」认养起步，样板田 → 村庄平台。
