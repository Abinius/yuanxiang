@php
  $current = request()->route()?->getName() ?? '';
@endphp

<div class="menu-area">
  <div class="menu-group">
    <div class="menu-group-label">概览</div>
    <a class="menu-link {{ $current === 'tenant.admin.dashboard' ? 'active' : '' }}"
       href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}"
       data-label="经营看板">
      <span class="menu-icon"><x-lucide-layout-dashboard /></span><span class="menu-text">经营看板</span>
    </a>
  </div>

  <div class="menu-group">
    <div class="menu-group-label">业务</div>
    <a class="menu-link {{ $current === 'tenant.admin.adoptions.index' ? 'active' : '' }}"
       href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}"
       data-label="认养订单">
      <span class="menu-icon"><x-lucide-sprout /></span><span class="menu-text">认养订单</span>
    </a>
    <a class="menu-link {{ $current === 'tenant.admin.farm-logs.index' ? 'active' : '' }}"
       href="{{ route('tenant.admin.farm-logs.index', ['tenant' => $tenant->slug]) }}"
       data-label="农事内容">
      <span class="menu-icon"><x-lucide-notebook-pen /></span><span class="menu-text">农事内容</span>
    </a>
    <a class="menu-link {{ str_starts_with($current, 'tenant.admin.cameras') ? 'active' : '' }}"
       href="{{ route('tenant.admin.cameras.index', ['tenant' => $tenant->slug]) }}"
       data-label="摄像头">
      <span class="menu-icon"><x-lucide-video /></span><span class="menu-text">摄像头</span>
    </a>
    <a class="menu-link {{ str_starts_with($current, 'tenant.admin.trace-codes') ? 'active' : '' }}"
       href="{{ route('tenant.admin.trace-codes.index', ['tenant' => $tenant->slug]) }}"
       data-label="溯源码">
      <span class="menu-icon"><x-lucide-qr-code /></span><span class="menu-text">溯源码</span>
    </a>
    <a class="menu-link {{ str_starts_with($current, 'tenant.admin.deliveries') ? 'active' : '' }}"
       href="{{ route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug]) }}"
       data-label="配送管理">
      <span class="menu-icon"><x-lucide-truck /></span><span class="menu-text">配送管理</span>
    </a>
  </div>

  <div class="menu-group">
    <div class="menu-group-label">保障</div>
    <a class="menu-link {{ $current === 'tenant.admin.adjustments.index' ? 'active' : '' }}"
       href="{{ route('tenant.admin.adjustments.index', ['tenant' => $tenant->slug]) }}"
       data-label="补退管理">
      <span class="menu-icon"><x-lucide-scale /></span><span class="menu-text">补退管理</span>
    </a>
    <a class="menu-link {{ $current === 'tenant.admin.gift-boxes.index' ? 'active' : '' }}"
       href="{{ route('tenant.admin.gift-boxes.index', ['tenant' => $tenant->slug]) }}"
       data-label="礼盒">
      <span class="menu-icon"><x-lucide-gift /></span><span class="menu-text">礼盒</span>
    </a>
    <a class="menu-link {{ str_starts_with($current, 'tenant.admin.promotions') ? 'active' : '' }}"
       href="{{ route('tenant.admin.promotions.index', ['tenant' => $tenant->slug]) }}"
       data-label="促销">
      <span class="menu-icon"><x-lucide-megaphone /></span><span class="menu-text">促销</span>
    </a>
  </div>

  <div class="menu-group">
    <div class="menu-group-label">设置</div>
    <a class="menu-link {{ $current === 'tenant.admin.settings.edit' ? 'active' : '' }}"
       href="{{ route('tenant.admin.settings.edit', ['tenant' => $tenant->slug]) }}"
       data-label="站点设置">
      <span class="menu-icon"><x-lucide-settings /></span><span class="menu-text">站点设置</span>
    </a>
    <a class="menu-link {{ str_starts_with($current, 'tenant.admin.short-links') ? 'active' : '' }}"
       href="{{ route('tenant.admin.short-links.index', ['tenant' => $tenant->slug]) }}"
       data-label="短链接">
      <span class="menu-icon"><x-lucide-link /></span><span class="menu-text">短链接</span>
    </a>
  </div>

  <div class="menu-group menu-group-soft">
    <div class="menu-group-label">切换</div>
    <a class="menu-link" href="{{ route('tenant.family.dashboard', ['tenant' => $tenant->slug]) }}"
       data-label="家人端">
      <span class="menu-icon"><x-lucide-users /></span><span class="menu-text">家人端</span>
    </a>
    <a class="menu-link" href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}"
       data-label="前台">
      <span class="menu-icon"><x-lucide-home /></span><span class="menu-text">前台</span>
    </a>
  </div>
</div>
