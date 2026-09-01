# 云乡 项目评审报告 — 代码质量 & UI 视觉

**评审对象**：`D:\Abin\abincheung\WEB\云乡`（Laravel 12 + Blade + Tailwind v4，多租户 SaaS 枸杞认养平台）
**评审日期**：2026-09-01
**评审范围**：全部 app/（Models/Services/Controllers/Middleware/Enums/Jobs/Tenancy）、database/（14 迁移 + 10 种子 + Factory）、resources/（1455 行 CSS 设计系统 + 50 个 Blade 视图 + JS）、routes/、tests/（25 个 Feature 测试，约 156 用例）、项目根配置与 4 份文档
**评审方式**：3 个子代理并行 + 主持方交叉验证（确认了 1 个关键前端发现的真实性）

---

## 总评

这是一份**明显高于同规模项目平均水准**的代码。服务层边界清晰、状态机守卫完备、多租户隔离意识极强（每处都有显式 `tenant_id` 守卫，并有跨租户 403/404 专项测试）、测试组织优秀。

**但存在 3 个必须在上线前修复的硬伤**：
1. 🔴 整套新设计系统（1455 行 CSS）**从未构建上线**——线上跑的仍是 129 行老文件，导致 C 端/后台大面积裸样式、租户品牌色失效
2. 🔴 下单「查重→插入」存在 TOCTOU 竞态，**同一田块可能超卖**
3. 🔴 登录/绑定/扫码**无速率限制** + `WECHAT_MOCK=true` 默认值 = 生产静默 mock 开关

---

## 一、🔴 严重（P0，上线前必须修复）

### 1.1 新设计系统未构建上线（前端 · 已验证）
- `resources/css/app.css`（1455 行）包含全部 `--color-brand-*` / `--ds-*` token 和 `.nav` / `.card` / `.btn-primary` / `.login-page` / `.sidebar` / `.trace-timeline` 等组件 class
- `public/app.css`（129 行）是老的静态样式，`<link>` 直接引用它。**5 个入口**：`layouts/site.blade.php:12`、`layouts/dashboard.blade.php:11`、`welcome.blade.php:6`、`auth/login.blade.php:6`、`platform/login.blade.php:6`
- 验证：`public/app.css` 里 grep `--color-brand` 返回空；页面用的 `.nav`/`.card`/`.btn-primary`/`.login-page` 老文件里也完全没有
- **影响**：welcome 平台入口整页破版（内联 style 引用的 `--ds-*/--color-brand-*` 全部未定义）；首页 hero/流程/时间线/胶囊裸样式；登录页裸奔；后台移动端抽屉不可用
- **修复**：
  ```bash
  npm install && npm run build          # 生成 public/build/
  ```
  然后把 Blade 里的 `<link rel="stylesheet" href="{{ asset('app.css') }}">` 改成 `@vite('resources/css/app.css')`；删除 `public/app.css`（或 gitignore），避免双 CSS 加载。

### 1.2 下单「查重→插入」竞态 → 田块超卖（后端 · 严重）
- `app/Services/AdoptionService.php:39-52` 先 `exists()` 判断「本季已被认养」，再 `:69-82` `Adoption::create()`，两步间无唯一约束或行锁
- `database/migrations/0001_01_01_000700_create_adoption_tables.php:38-39` 只有 `index(['adoptable_type','adoptable_id'])`，**没有唯一约束**
- 两个用户同时下单 → 都通过 exists → 两张 active 认养单 → 同一块田被卖两次
- **修复**：加 `unique(['adoptable_type','adoptable_id','season_year'])`（SQLite 需对 cancelled 复用旧行或改列），并把整个 `createOrder` 包进 `DB::transaction()` + `lockForUpdate()`

### 1.3 登录/绑定/扫码无速率限制（安全 · 严重）
- `routes/web.php:73-78` 登录路由、`routes/platform.php:9-11` 平台登录、`/s/{code}` 扫码、`/u/{code}` 短链、`bindPhone` 均无 `throttle`
- `产品开发文档.md:140` 明写「登录限流（throttle）」，但代码未落地
- **修复**：登录路由 `->middleware('throttle:5,1')`，扫码 `throttle:30,1`，短链 `throttle:60,1`，并补测试

### 1.4 `WECHAT_MOCK=true` 默认值 = 生产静默 mock 开关（配置 · 严重）
- `config/wechat.php` 默认 `env('WECHAT_MOCK', true)`
- 若部署遗漏，支付退款会静默"成功"却不真退、登录走 mock 建号——**资金与身份双失效且无报错**
- **修复**：`.env.example` 显式 `WECHAT_MOCK=false`，上线走查清单加「必须显式置 false」校验，考虑「非 local + mock=true」告警

---

## 二、🟠 高优（P1，强烈建议上线前修）

### 2.1 租户上下文静态状态在队列中的生命周期（架构 · 中危）
- `TenantContext.php:10` 用 `private static ?int $tenantId`，PHP-FPM 每请求安全，但**队列 worker 是长生命周期进程**
- 目前两个 Job 靠显式 `->where('tenant_id', ...)` 规避了，但未来任何 Job 直接 `Model::create()` 且不带 `tenant_id` 就会串租户或抛异常
- **修复**：Job 基类统一 `setUp`/`finally` 清理上下文，或把租户上下文改为按 Job 作用域注入

### 2.2 路由模型绑定先于租户中间件（架构 · 中）
- `SubstituteBindings` 先于 `TenantMiddleware` 执行，此时 `TenantScoped` 全局作用域未激活，`{plot}`/`{adoption}`/`{camera}` 等绑定到的是**全库记录**
- 代码靠每个方法手写 `abort_if($x->tenant_id !== $tenant->id, 404)` 兜底（`MyPlotController.php:22-25`、`TraceController.php:14-16` 等都有注释承认）
- 现有守卫基本齐全，但这是"靠纪律而非靠框架"——新增 Controller 忘记写就产生跨租户 IDOR
- **修复**：把租户解析提前到绑定前，或抽成 trait/基类方法

### 2.3 优惠券过期/库存逻辑缺失 + 未测（后端 · 中）
- `PromotionService::discountFor` 未校验 `coupon->expires_at`（字段存在但从未消费）、未扣减 `promotion->stock`
- 过期券仍可用、库存不生效；测试只覆盖 amount 券，无 percent/min_condition/过期/库存测试
- **修复**：补校验逻辑 + 补测试

### 2.4 微信 access_token 每次重新请求（性能 · 中）
- `WechatTemplateService.php:37-41` 每发送一次就 `Http::get('/cgi-bin/token')`
- 批量推送给 N 个认养人 = N 次 token 请求，易命中微信 token 每日 2000 次上限
- **修复**：token 缓存到 `cache()`（2h 有效期），Job 内复用

### 2.5 设计系统租户品牌色覆盖是死代码（前端 · 高）
- `layouts/site.blade.php:16-18` 注入了 `--primary`/`--accent` 覆盖，但 `resources/css/app.css` 全部使用 `--color-brand-*` token，**从未引用** `--primary`/`--accent`
- 多租户品牌化失效；每个租户看起来都一样
- **修复**：CSS 里把品牌色引用改为 `var(--primary)` / `var(--accent)`，或建立 token 到覆盖值的映射

### 2.6 表单输入字号 15px（前端 · 高）
- `resources/css/app.css:615` `.input` font-size 为 `var(--ds-body)` = 15px
- iOS Safari 会把 <16px 的输入框**自动缩放到 16px**，导致布局错位、视觉不一致
- **修复**：表单输入/选择/文本域字号提到 16px

### 2.7 `annual_fee` 金额精度不一致（后端 · 中）
- 迁移里 `annual_fee` 是 `unsignedInteger`，`AdoptionService` 用 `(float)` 处理，`WeChatPayService` 再 `bcmul(..., '100')` 转分
- 若出现 ¥199.50 这种小数，unsignedInteger 会**截断**为 199
- **修复**：迁移改 `decimal(10,2)`，或统一存"分"为整数

---

## 三、🟡 中优（P2，建议尽快修）

### 3.1 移动端 `.nav` 导航缺少汉堡菜单断点（前端）
- `.nav` 在 `resources/css/app.css:346-358` 是桌面 flex 布局，`@media (max-width: 840px)` 和 `520px` 断点里**没有 `.nav` 的响应式规则**
- 微信内导航链接会换行/挤压，无折叠收口
- **修复**：在 840px 断点补 `.nav { flex-wrap: wrap; gap: 8px; }` + 小屏折叠收口

### 3.2 字体从 Google Fonts 加载（前端 · 性能/微信）
- `resources/css/app.css:21-28` 的 `@font-face` 直接引 `https://fonts.googleapis.com`
- 微信内字体加载不稳，`Noto Serif SC` 只用了 `local()` fallback
- **修复**：自托管字体（base64 或 assets），或加系统字体 fallback 链

### 3.3 页面大量内联 style（前端 · 可维护性）
- `site/home.blade.php`、`site/adopt/show.blade.php`、`site/my/plot.blade.php` 等页面充斥 `style="..."` 内联样式，绕过了 CSS 设计系统的 token
- 与"Blade 直接套 @apply 组件 class，不再写内联 style"（`app.css:9`）的设计目标相悖
- **修复**：把内联样式抽到组件 class 或用 `@apply`

### 3.4 Dashboard 聚合无缓存（性能）
- `Admin/DashboardController.php:29-69` 单请求约 15 次 count/sum 查询，`seasonStats` 循环内再查
- **修复**：`Cache::remember` 5 分钟（按 tenant 加 key 前缀）

### 3.5 sitemap/robots 每请求实时生成（性能）
- `routes/web.php:41-61` 每次遍历全部 active 租户拼 XML
- **修复**：`Cache::remember` 或 schedule 预生成

### 3.6 `autoRenew` 无状态守卫（后端 · 中）
- `MyPlotController.php:113-119` `autoRenew` 只校验 tenant_id/user_id，不校验认养状态
- 已取消/过期的认养也能"开启续费意向"，语义错误
- **修复**：加 `abort_unless($adoption->status === AdoptionStatus::Active, ...)`

### 3.7 CSRF 从未被真实断言（测试）
- 所有 POST 测试都无 CSRF token 通过，无法证明生产环境 CSRF 拦截有效
- **修复**：补一个"无 CSRF token 的 POST 被 419 拦截"冒烟测试

### 3.8 SQLite 下外键无自动索引（性能）
- MySQL 外键自动建索引，SQLite 不会。`deliveries.harvest_id`、`farm_logs.plot_id` 等高频过滤列在 SQLite 缺索引

### 3.9 并发/竞态路径完全无测试（测试）
- 对应 1.2 的超卖竞态，没有任何测试覆盖
- **修复**：补并发模拟用例，或至少用唯一索引约束测违反时的降级

---

## 四、🔵 低优/改进建议

### 4.1 单元测试几乎缺失
- `tests/Unit/ExampleTest.php` 是空壳，全部逻辑测试都是 Feature 测试经 HTTP 驱动
- Service 层（`AdjustmentService` 保底规则、`GiftBoxService` 额度、`TraceService` 节点合并、`SeoService` 三层覆盖）值得补纯单元测试

### 4.2 `platform.php` 平台后台能力单薄
- 只有 `/platform` 仪表盘一个受保护路由，与"多租户 SaaS 平台"的产品定位差距较大
- 文档（`开发计划.md:50`）诚实标注为未开始，但上线前应明确平台后台范围

### 4.3 `/u/{code}` 开放跳转无白名单（安全 · 低-中）
- admin 被攻破即可制造钓鱼跳转；建议校验 `https?://` + 可选域名白名单

### 4.4 `.env.example` 缺生产模板
- `APP_DEBUG=true` 若照抄到生产会暴露路径/凭证；`SESSION_SECURE_COOKIE` 未出现

### 4.5 文档测试数字漂移
- README：156 tests / 513 assertions
- 开发计划：143 tests（461 assertions）/ 128 tests（420 assertions）两处
- 上线走查：128 / 143 两处
- **修复**：统一以 `php artisan test` 实跑数为准

### 4.6 产品开发文档技术栈过时
- 写"Blade + Alpine.js + Tailwind SSR（零构建）"、"Filament 或 daisyUI"
- 实际是 Vite + Tailwind v4 构建、后台手写 Blade
- 工程根目录路径也过时（`D:\Abin\云乡` → `D:\Abin\abincheung\WEB\云乡`）

### 4.7 前端零散问题清单（视觉子代理补充）

| 问题 | 位置 | 说明 |
|---|---|---|
| `@font-face` 用 Google CSS 文档 URL 当字体文件 | `app.css:21-28` | `src: url('https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&display=swap')` 是 CSS 文档而非字体文件，完全无效；国内不可达 |
| `nav {@apply hidden}` hack | `app.css:1054` | 把全局 `<nav>` 设为 hidden，靠 `.nav-admin`/`.nav` 单独覆盖——语义冲突、易误伤 |
| JS bundle 从未加载 | 所有视图 | `app.js`/`bootstrap.js` 只定义了 `window.axios`，但页面只 `<link>` CSS，JS bundle 从未被 `@vite` 引用 |
| family 录入 3 页用裸元素选择器 | `family/farm_log/create.blade.php` 等 | 直接写 `<h2>`/`<input>` 不带 class，新 CSS 上线即失样式 |
| 116 处内联 `var(--ds-*)` | 各 Blade 视图 | 绕过组件 class，与设计系统目标相悖 |
| login tab 无 `aria-selected`/键盘支持 | `auth/login.blade.php` | 缺 `role=tab`、`aria-selected`、Tab/Arrow 键导航 |
| referral 复制反馈 `this` 指向错误 | `site/my/referral.blade.php` | 内联 `onclick` 里 `this` 指向 `<button>` 而非目标元素 |
| `hls.js@latest`/`qrcodejs` 未锁版本无 SRI | `site/live/*` | CDN 加载不稳定且无 integrity 校验，有供应链风险 |
| 后台 `nav_right` 死代码 | `layouts/dashboard.blade.php` | `@yield('nav_right', '')` 从未被任何子视图填充 |
| 页脚只在首页 | `site/home.blade.php` | 其他页面（认养、我的田、溯源、礼盒）无 footer |

---

## 五、架构与设计亮点（值得保留的资产）

1. **多租户隔离意识极强**：`TenantScoped` 全局作用域 + 每个控制器显式守卫 + `TenantMemberMiddleware`/`RoleMiddleware` 双层防越权，且有跨租户 403/404 专项测试
2. **支付/退款幂等设计到位**：`markPaid` 只处理 pending 支付，重复回调/退款被显式断言；补退用确定性 `out_refund_no` 防微信侧重复退费
3. **服务层边界清晰、状态机守卫完备**：Controller 保持薄，"非法状态 422"封装在 Service 层
4. **测试组织优秀**：25 个 Feature 文件按功能域命名，每个 sprint 都有对应测试；`TenantContext::reset()` 在 `setUp` 统一清理
5. **数据库设计符合演进式迁移**：34 表结构清晰，`sprint3_ops` 用 `Schema::table` 追加列
6. **合规文案自律**：代码注释与测试贯彻"cert_status=not_started，不写有机认证"口径，并映射为测试断言
7. **设计系统本身内容质量高**：`resources/css/app.css` 1455 行含完整的 token 分层（品牌色 9 级 + 语义色 + dsh 版式令牌）、组件库（卡片/按钮 5 态/表单/标签/提示/表格/分页/后台抽屉/铭牌/溯源时间线/生长日历/打印方案）、品牌三色与 dsh 语法对齐——**属于"好东西没发出去"而非"东西本身差"**

---

## 六、修复优先级速查表

| 优先级 | 事项 | 位置 |
|---|---|---|
| 🔴 P0 | 新设计系统构建上线（`@vite` 替换 `<link>`） | 1.1 |
| 🔴 P0 | 下单竞态 + 唯一约束防超卖 | 1.2 / `AdoptionService` |
| 🔴 P0 | 登录/扫码/短链/绑定无限流 | 1.3 / routes |
| 🔴 P0 | `WECHAT_MOCK` 生产必须显式 false | 1.4 |
| 🟠 P1 | 租户上下文在队列中的生命周期治理 | 2.1 |
| 🟠 P1 | 路由绑定先于租户中间件的架构脆性 | 2.2 |
| 🟠 P1 | 券过期/库存逻辑缺失 + 补测 | 2.3 |
| 🟠 P1 | access_token 缓存 | 2.4 |
| 🟠 P1 | 租户品牌色覆盖打通 | 2.5 |
| 🟠 P1 | 表单字号 16px | 2.6 |
| 🟠 P1 | 金额精度 decimal 或统一存分 | 2.7 |
| 🟡 P2 | 移动端 `.nav` 汉堡菜单断点 | 3.1 |
| 🟡 P2 | 字体自托管 / fallback 链 | 3.2 |
| 🟡 P2 | 内联样式收敛到组件 class | 3.3 |
| 🟡 P2 | Dashboard/sitemap 缓存 | 3.4 / 3.5 |
| 🟡 P2 | `autoRenew` 状态守卫 + CSRF 冒烟测试 + 单元测试 | 3.6 / 3.7 / 4.1 |
| 🔵 P3 | 文档数字统一、技术栈描述更新 | 4.5 / 4.6 |
| 🔵 P3 | 前端零散问题（@font-face 无效 / nav hack / JS bundle 未加载 / 裸元素 / 内联 var / 缺 aria / 复制 this / CDN 无锁 / 死代码 / 页脚缺失） | 4.7 |
| 🔵 P3 | 对比度不足（--ds-text-mute ~3.5:1、tag-warn 2.6:1、tag-danger 3.1:1）+ 后台 `.num/.label` class 错配 | 3.2 补充 |

---

> 本报告基于对全部代码与文档的实际读取，所有问题均引用具体文件与行号。前端子代理的关键发现（新设计系统未构建上线）已由主持方独立验证确认。
