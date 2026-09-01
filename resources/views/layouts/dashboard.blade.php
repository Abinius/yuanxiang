@php
  $brand = $tenant->settings['brand'] ?? config('site.defaults.brand');
  $routeName = request()->route()?->getName() ?? '';
  $isAdmin = str_starts_with($routeName, 'tenant.admin.');
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="{{ asset('app.css') }}">
<title>@yield('title', '后台') · {{ $tenant->name ?? '光彩云村庄平台' }}</title>
@stack('head')
</head>
<body>
<nav class="nav-admin">
  <div class="nav-left">
    @if ($isAdmin)
      <button class="nav-toggle" id="sidebar-open-btn" type="button" aria-label="打开菜单" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 6h18M3 12h18M3 18h18"/>
        </svg>
      </button>
    @endif
    <span class="brand">{{ $tenant->name ?? '光彩云村庄平台' }}</span>
  </div>
  <div class="nav-right">
    @if ($isAdmin)
      <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
      <a href="{{ route('tenant.family.dashboard', ['tenant' => $tenant->slug]) }}">家人端</a>
      <span class="user">{{ auth()->user()->nickname }}</span>
      <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenant->slug]) }}" class="inline">
        @csrf
        <button type="submit">退出</button>
      </form>
    @else
      @yield('nav_right', '')
    @endif
  </div>
</nav>

<div class="layout {{ $isAdmin ? 'layout-admin' : '' }}">
  @if ($isAdmin)
    <div class="admin-shell">
      <aside class="sidebar" id="admin-sidebar">
        <div class="sidebar-head">
          <span class="sidebar-title">云乡后台</span>
        </div>
        @include('admin.partials.menu')
        <div class="sidebar-foot">
          <button class="sidebar-toggle" id="sidebar-collapse-btn" type="button" title="折叠/展开侧栏">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h13M3 12h13M3 18h13"/>
              <path d="M19 9l4 3-4 3"/>
            </svg>
          </button>
        </div>
      </aside>
      <main class="admin-main">
        @yield('content')
      </main>
    </div>
  @else
    <main class="admin-main" style="max-width:none">
      @yield('content')
    </main>
  @endif
</div>
@if ($isAdmin)
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
@endif

<script>
(function () {
  var btn = document.getElementById('sidebar-collapse-btn');
  var sb  = document.getElementById('admin-sidebar');
  if (!btn || !sb) return;

  var collapsed = (localStorage.getItem('yuanxiang-sidebar-collapsed') === '1');
  function apply(state) {
    sb.classList.toggle('collapsed', state);
    btn.setAttribute('aria-expanded', String(!state));
    btn.setAttribute('title', state ? '展开侧栏' : '折叠侧栏');
    localStorage.setItem('yuanxiang-sidebar-collapsed', state ? '1' : '0');

    // 折叠态:给每个菜单链接加 tooltip = data-label
    sb.querySelectorAll('a[data-label]').forEach(function (a) {
      a.setAttribute('title', a.getAttribute('data-label') || '');
    });
  }
  apply(collapsed);

  btn.addEventListener('click', function () {
    apply(!sb.classList.contains('collapsed'));
  });
})();
// 移动端抽屉:与桌面折叠状态独立,默认收起,加 .open 展开
(function () {
  var openBtn = document.getElementById('sidebar-open-btn');
  var backdrop = document.getElementById('sidebar-backdrop');
  var sb = document.getElementById('admin-sidebar');
  if (!openBtn || !backdrop || !sb) return;
  function open() {
    sb.classList.add('open');
    backdrop.classList.add('open');
    openBtn.setAttribute('aria-expanded', 'true');
  }
  function close() {
    sb.classList.remove('open');
    backdrop.classList.remove('open');
    openBtn.setAttribute('aria-expanded', 'false');
  }
  openBtn.addEventListener('click', function () {
    if (sb.classList.contains('open')) close(); else open();
  });
  backdrop.addEventListener('click', close);
  sb.querySelectorAll('.menu-link').forEach(function (a) {
    a.addEventListener('click', close);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sb.classList.contains('open')) close();
  });
  // 放大到桌面时清掉抽屉的 .open,避免残留
  window.matchMedia('(min-width: 841px)').addEventListener('change', function (e) {
    if (e.matches) close();
  });
})();
</script>
</body>
</html>