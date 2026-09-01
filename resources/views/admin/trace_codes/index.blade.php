@extends('layouts.dashboard')

@section('title', '溯源码管理')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">溯源码管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.trace-codes.create', ['tenant' => $tenant->slug]) }}">+ 生成溯源码</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  <p class="note mb-5">
    <b class="serif text-brand">每箱一码</b>:为一次采收生成多条箱码,扫码看本箱地块全程;二维码在打印页由浏览器生成。
  </p>

  @if ($traceCodes->isEmpty())
    <div class="empty">
      <div class="empty-icon">🔍</div>
      <div>还没有溯源码。</div>
      <div class="empty-hint">点击「生成溯源码」为采收生成箱级追溯码,扫码即见全程。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>溯源码</th><th>采收</th><th>田块</th><th>扫码次数</th><th>操作</th></tr>
        </thead>
        <tbody>
          @foreach ($traceCodes as $tc)
            <tr>
              <td class="mono font-medium">{{ $tc->code }}</td>
              <td class="text-xs">
                {{ $tc->harvest?->season_year ?? '—' }} 年度
                @if ($tc->harvest?->plot?->code)
                  <span class="muted">({{ $tc->harvest->plot->code }})</span>
                @endif
              </td>
              <td>{{ $tc->plot?->code ?? '—' }}</td>
              <td>
                <span class="tag tag-renew">
                  {{ $tc->scanned_count }} 次
                </span>
              </td>
              <td>
                <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.trace-codes.print', ['tenant' => $tenant->slug, 'ids' => $tc->id]) }}">打印</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection