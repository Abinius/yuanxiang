@extends('layouts.dashboard')

@section('title', '家人端')
@section('nav_right')
  @if (auth()->user()->role->value === 'tenant_admin')
    <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}">管理后台</a>
  @endif
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenant->slug]) }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  @php
    $scopeLabels = [
      'farm_log'   => '农事动态 / 直播预告',
      'fertilizer' => '有机肥批次',
      'harvest'    => '采收',
    ];
    $scopeNames = array_map(fn ($s) => $scopeLabels[$s] ?? $s, $scopes ?? []);
  @endphp

  <h1 class="hero-title mb-2" style="font-size:var(--ds-h2)">{{ $tenant->name }} · 家人录入端</h1>
  <p class="lede mb-4">
    当前权限:{{ $scopes ? implode(' / ', $scopeNames) : '<span class="text-warn font-medium">暂无</span>(请联系管理员配置 farm_members.permission_scope)' }}
  </p>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  @php
    $links = [
      'farm_log'   => ['发农事动态 / 直播预告', '📝', route('tenant.family.logs.create',     ['tenant' => $tenant->slug])],
      'fertilizer' => ['录有机肥批次',          '🧪', route('tenant.family.fertilizer.create', ['tenant' => $tenant->slug])],
      'harvest'    => ['录采收',               '🌾', route('tenant.family.harvest.create',    ['tenant' => $tenant->slug])],
    ];
  @endphp

  <div class="card-grid grid-3 mb-6">
    @foreach ($links as $key => [$label, $icon, $url])
      @if (in_array($key, $scopes))
        <a class="card card-link" href="{{ $url }}">
          <div class="num sm serif text-brand">{{ $icon }}</div>
          <div class="label font-medium">{{ $label }}</div>
          <span class="tag tag-dot tag-available mt-2">可用</span>
        </a>
      @else
        <div class="card card-soft">
          <div class="num sm" style="color:var(--ds-text-mute)">{{ $icon }}</div>
          <div class="label">— {{ $label }}</div>
          <span class="tag tag-off mt-2">未授权</span>
        </div>
      @endif
    @endforeach
  </div>

  <p class="note text-xs mb-5">
    拍照发动态 / 录肥批次 / 录采收 / 直播预告;动态默认对云乡民可见。
  </p>

  <section class="section">
    <div class="section-title"><span>最近录入</span></div>

    <div class="mb-3">
      <h3 class="text-sm font-medium mb-2">农事动态</h3>
      @forelse ($recentLogs as $log)
        <div class="card mb-1" style="padding:12px 16px;margin-bottom:8px">
          <div class="flex justify-between items-center">
            <span class="font-medium">{{ $log->title }}</span>
            <span class="muted text-xs">{{ $log->occurred_at?->format('Y-m-d') }}</span>
          </div>
          <div class="muted text-xs mt-1">
            {{ $log->plot?->code ?? '—' }} · {{ $log->type->label() }}
            {{ $log->is_public ? '' : ' <span class="tag tag-off">私密</span>' }}
          </div>
        </div>
      @empty
        <p class="note text-xs">还没有农事记录。</p>
      @endforelse
    </div>

    <div class="mb-3">
      <h3 class="text-sm font-medium mb-2">有机肥批次</h3>
      @forelse ($recentBatches as $batch)
        <div class="card mb-1" style="padding:12px 16px;margin-bottom:8px">
          <div class="flex justify-between items-center">
            <span class="font-medium mono">{{ $batch->batch_no }}</span>
            <span class="muted text-xs">{{ $batch->produced_at?->format('Y-m-d') }}</span>
          </div>
          <div class="muted text-xs mt-1">
            {{ $batch->nxlb_ref ? 'NXLB '.$batch->nxlb_ref : 'NXLB 投入品批次' }}
          </div>
        </div>
      @empty
        <p class="note text-xs">还没有肥批次。</p>
      @endforelse
    </div>

    <div>
      <h3 class="text-sm font-medium mb-2">采收</h3>
      @forelse ($recentHarvests as $harvest)
        <div class="card mb-1" style="padding:12px 16px;margin-bottom:8px">
          <div class="flex justify-between items-center">
            <span class="font-medium">{{ $harvest->plot?->code ?? '—' }}</span>
            <span class="muted text-xs">{{ $harvest->harvested_at?->format('Y-m-d') }}</span>
          </div>
          <div class="muted text-xs mt-1">
            {{ $harvest->season_year }} 年度 · {{ $harvest->dry_weight_kg }} kg
          </div>
        </div>
      @empty
        <p class="note text-xs">还没有采收记录。</p>
      @endforelse
    </div>
  </section>
@endsection