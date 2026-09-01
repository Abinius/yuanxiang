@extends('layouts.site')

@section('title', '我的田 · ' . $adoption->named_label)

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">我的田</h1>
      <a class="back-link" href="{{ route('tenant.my.index', ['tenant' => $tenant->slug]) }}">我的认养</a>
    </div>

    @include('site.partials.nameplate', ['adoption' => $adoption])

    <div class="flex justify-end gap-2 mt-3">
      <a class="btn btn-ghost btn-sm" href="{{ route('tenant.my.gift.index', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">节日礼盒</a>
      <a class="btn btn-ghost btn-sm" href="{{ route('tenant.my.nameplate', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">分享铭牌</a>
    </div>

    <h2 style="margin:24px 0 8px;font-size:var(--ds-h3)">本季生长日历</h2>
    <p class="text-xs" style="color:var(--ds-text-mute);margin-bottom:10px">
      阶段为红寺堡产区枸杞近似物候,可依农艺数据调整;圆点为当月农事记录数。
    </p>
    <div class="calendar">
      @foreach ($calendar['months'] as $m)
        <div class="month {{ $m['is_today'] ? 'today' : '' }}">
          <div class="m-name">{{ $m['month'] }}月</div>
          <div class="m-label" style="color:{{ $m['color'] }}">{{ $m['label'] }}</div>
          <div class="m-dots">{{ $m['events'] ? str_repeat('●', min($m['events'], 3)) : '·' }}</div>
          @if ($m['is_today'])
            <div class="m-today">今天</div>
          @endif
        </div>
      @endforeach
    </div>

    <h2 style="margin:24px 0 8px;font-size:var(--ds-h3)">配送进度</h2>
    @forelse ($adoption->deliveries as $d)
      <div class="sub-card mb-3">
        <div class="flex justify-between items-center">
          <span class="tag {{ $d->status->value === 'delivered' ? 'tag-available' : ($d->status->value === 'shipped' ? 'tag-renew' : 'tag-off') }}">
            {{ $d->status->label() }}
          </span>
          <span class="text-xs muted">{{ $d->harvest?->season_year ?? '' }} 年度采收</span>
        </div>
        <div class="text-sm mt-2" style="color:var(--ds-text-soft);line-height:1.8">
          @if ($d->tracking_no) 运单号:{{ $d->tracking_no }}({{ $d->carrier ?? '承运中' }})<br>@endif
          @if ($d->address) 收货:{{ $d->address->name }} · {{ $d->address->phone }} {{ $d->address->detail }}@endif
        </div>
        @if ($d->status->value === 'shipped')
          <form method="POST" action="{{ route('tenant.my.delivery.receive', ['tenant' => $tenant->slug, 'adoption' => $adoption, 'delivery' => $d]) }}" style="margin-top:8px">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit">确认收货</button>
          </form>
        @endif
      </div>
    @empty
      <p class="note text-xs">本季采收待发,家人采收后会自动为你打包寄送。</p>
    @endforelse

    <h2 style="margin:24px 0 8px;font-size:var(--ds-h3)">农事动态</h2>
    @forelse ($logs as $log)
      <div class="sub-card mb-3">
        <div class="flex justify-between items-center">
          <span class="tag" style="background:var(--color-brand-50);color:var(--color-brand-500)">{{ $log->type->label() }}</span>
          <span class="text-xs muted">{{ $log->occurred_at->format('Y-m-d') }}</span>
        </div>
        <div class="font-medium mt-2">{{ $log->title }}</div>
        @if ($log->content)
          <p class="text-sm mt-1" style="color:var(--ds-text-soft)">{{ $log->content }}</p>
        @endif
        @if ($log->author)
          <div class="text-xs muted mt-2">记录人:{{ $log->author->nickname }}</div>
        @endif
      </div>
    @empty
      <p class="note text-xs">家人暂未录入农事动态,采收季会有更新。</p>
    @endforelse
  </div>
@endsection