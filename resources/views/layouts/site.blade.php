@php
  $seo = \App\Services\SeoService::fromTenant($tenant, $seo ?? []);
  $brand = $tenant->settings['brand'] ?? config('site.defaults.brand');
  $pageTitle = trim((string) $__env->yieldContent('title'));
  $seo['title'] = $pageTitle ? $pageTitle.' · '.$tenant->name : ($seo['title'] ?: $tenant->name);
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@vite('resources/css/app.css')
<style>
  /* 租户品牌色覆盖：app.css 为静态令牌，后台可配的覆盖值在此注入。
     把 --primary/--accent 映射到 Tailwind 用的 --color-brand-*/--color-accent-*。 */
  :root{
    --primary:{{ $brand['primary'] ?? config('site.defaults.brand.primary') }};
    --accent:{{ $brand['accent'] ?? config('site.defaults.brand.accent') }};
    --color-brand-500: var(--primary);
    --color-brand-600: var(--primary);
    --color-brand-700: var(--primary);
    --color-accent-500: var(--accent);
    --color-accent-400: var(--accent);
  }
</style>
@include('site.partials.seo')
</head>
<body>
<nav class="nav">
  <a class="brand" href="{{ route('tenant.home', []) }}">
    <span class="brand-dot" aria-hidden="true"></span>
    {{ $tenant->name }}
  </a>
  <div class="nav-links">
    <a href="{{ route('tenant.adopt.index', []) }}">认养田地</a>
    <a href="{{ route('tenant.live.index', []) }}">实时监控</a>
    @auth
      <a href="{{ route('tenant.my.index', []) }}">我的田</a>
      @if (in_array(auth()->user()->role->value, ['family', 'tenant_admin'], true))
        <a href="{{ route('tenant.family.dashboard', []) }}">家人后台</a>
      @endif
      @if (auth()->user()->role->value === 'tenant_admin')
        <a href="{{ route('tenant.admin.dashboard', []) }}">管理后台</a>
      @endif
      <span class="user">{{ auth()->user()->nickname ?? auth()->user()->phone }}</span>
      <form method="POST" action="{{ route('tenant.logout', []) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm">退出</button>
      </form>
    @else
      <a href="{{ route('tenant.login', []) }}">登录</a>
    @endauth
  </div>
</nav>
<main class="main">
  @yield('content')
</main>
</body>
</html>