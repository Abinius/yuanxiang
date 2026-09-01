# 光彩云村庄 · 云乡

> 宁夏红寺堡枸杞认养 SaaS —— 云乡民在线认养一畦田，生态种植全程可溯源。

光彩云村庄平台（内部代号「云乡」）是一套面向认养农业的多租户 SaaS，以宁夏红寺堡枸杞为首个样板：城市用户（云乡民）在线认养一畦枸杞田，全程溯源、实时监控、定期配送、节日礼盒；家人端录入农事，后台统一经营。全链条内化（种植家庭 + 有机肥厂 + 在地劳力 + 品牌 + 平台）是核心差异化。

## 技术栈

- **Laravel 12**（PHP 8.2）+ **Blade** + **Tailwind v4**（**Vite 构建**，`@vite` 注入，`@theme` 语义色）—— 纯 Blade + 内联 JS，无 React/Vue
- **SQLite** 默认（可换 MySQL/PG）；**yansongda/laravel-pay**（微信支付）
- **mallardduck/blade-lucide-icons** —— `<x-lucide-*>` 图标组件
- 设计系统：dsh（deepseek-harness）版式语法 + 暖调品牌令牌（枸杞砖红 `#B33A26` / 金 `#C9A227` / 米底 `#FAF6F0`）
- 字体：Instrument Serif + Noto Serif SC（标题）/ Instrument Sans + Noto Sans SC（正文）/ SF Mono（代码）

## 架构

多租户 SaaS，路由前缀 `/t/{tenant:slug}`，`tenant` 中间件注入租户上下文，`role:tenant_admin` / `role:family,tenant_admin` 区分端，`tenant.member` 守卫防跨租户越权（认养人只能访问本人租户资源；公开页保持跨租户可分享）。

| 端 | 路由前缀 | 角色 | 能力 |
|---|---|---|---|
| 前台 site | `/t/{slug}` | 公开 + 认养人 | 认养下单/签约/支付、溯源时间线、溯源码扫码、礼盒收礼、我的田（生长日历/农事动态/续费/收货）、实时监控、短链接、铭牌分享 |
| 后台 admin | `/t/{slug}/admin` | `tenant_admin` | 经营看板、认养订单、农事内容、摄像头、溯源码、配送、补退、礼盒、促销、站点设置、短链接 |
| 家人端 family | `/t/{slug}/family` | `family` / `tenant_admin` | 农事录入、肥料批次、采收录入 |

平台公共：选店入口 `/`、微信支付回调 `/pay/wechat/notify`、`robots.txt`、`sitemap.xml`。

## 领域模型

Tenant · User · Plot · Farm · FarmMember · FarmLog · FertilizerBatch · Harvest · Adoption · Payment · Delivery · TraceCode · GiftBox · Promotion · Coupon · ShortLink · Settlement · Payout · CommissionRule · SubscriptionPlan · Plan · Organization · Address · DetectionReport · Post · PushMessage · AdoptionAdjustment · Camera

## 本地开发

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force          # SQLite，默认 database/database.sqlite
npm install && npm run build         # 或 npm run dev 热更新
php artisan serve                    # 127.0.0.1:8000
```

一键并发起服务（composer script）：

```bash
composer dev    # serve + queue:listen + pail + vite 并发
```

## 测试

```bash
php artisan config:clear && php artisan test
```

167 tests（含安全基线 + 保底规则引擎单元测试）。

## 设计系统

- `resources/css/app.css` —— `@theme` 语义色 + 组件 class 库（导航/卡片/按钮/表格/标签/空态/分页/表单），`--ds-*` token 系列
- `resources/views/components/design-system/` —— 共享 partials
- 两个 layout：`layouts/site.blade.php`（前台）+ `layouts/dashboard.blade.php`（admin/family）
- 后台侧栏：Lucide 图标，桌面折叠 + 移动端 off-canvas 抽屉（`display:contents` 修复栅格遮挡）

## 目录结构

```
app/Http/Controllers/
  Site/      前台（认养/溯源/我的田/直播/礼盒/短链/分享）
  Admin/     商户后台
  Family/    家人端录入
  Auth/      双登录（账密 + 微信）
  Pay/       微信支付
app/Models/  领域模型（TenantScoped）
resources/views/{site,admin,family,components,layouts}
routes/web.php
```

## 背景

宁夏红寺堡光彩村枸杞种植家庭出身，全链条内化：种植家庭 + 有机肥厂（NXLB）+ 在地农业劳力 + 品牌 + 平台。6 亩枸杞「云乡民」认养起步，样板田 → 村庄平台。
