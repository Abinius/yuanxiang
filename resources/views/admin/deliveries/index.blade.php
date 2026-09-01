@extends('layouts.dashboard')

@section('title', '配送管理')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">配送管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.deliveries.create', ['tenant' => $tenant->slug]) }}">+ 打单(选采收)</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-4">
    按采收为认养人生成配送单 → 录运单发货 → 认养人在「我的田」确认收货。
  </p>

  <div class="table-bar">
    <form method="GET" action="{{ route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug]) }}" style="display:flex;align-items:center;gap:8px;flex:1;max-width:260px">
      <label class="text-sm" style="margin:0">状态</label>
      <select name="status" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">全部</option>
        @foreach (\App\Enums\DeliveryStatus::cases() as $s)
          <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
        @endforeach
      </select>
      <noscript><button class="btn btn-ghost btn-sm" type="submit">筛选</button></noscript>
    </form>
    <div class="spacer"></div>
    <span class="note text-xs muted">共 {{ $deliveries->total() ?? 0 }} 条</span>
  </div>

  @if ($deliveries->isEmpty())
    <div class="empty">
      <div class="empty-icon">🚚</div>
      <div>还没有配送单。</div>
      <div class="empty-hint">点击「打单」按采收为认养人生成待发货配送单。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>认养单</th><th>认养人</th><th>田块</th><th>采收</th>
            <th>收货地址</th><th>状态</th><th>运单号</th><th>操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($deliveries as $d)
            <tr>
              <td class="mono">{{ $d->adoption?->adoption_no ?? '—' }}</td>
              <td>{{ $d->adoption?->user?->nickname ?? '—' }}</td>
              <td>{{ $d->harvest?->plot?->code ?? '—' }}</td>
              <td class="text-xs">{{ $d->harvest?->season_year ?? '—' }} 年度</td>
              <td class="text-xs">{{ $d->address ? $d->address->name.' · '.$d->address->phone : '—' }}</td>
              <td>
                <span class="tag {{ $d->status->value === 'delivered' ? 'tag-available' : ($d->status->value === 'shipped' ? 'tag-renew' : 'tag-warn') }}">
                  {{ $d->status->label() }}
                </span>
              </td>
              <td class="mono text-xs">{{ $d->tracking_no ?? '—' }}</td>
              <td>
                <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.deliveries.print', ['tenant' => $tenant->slug, 'ids' => $d->id]) }}">打单</a>
                @if ($d->status->value === 'pending')
                  <form method="POST" action="{{ route('tenant.admin.deliveries.ship', ['tenant' => $tenant->slug, 'delivery' => $d]) }}" class="flex gap-1">
                    @csrf
                    <input class="input" name="tracking_no" placeholder="运单号" required style="width:110px;font-size:12px;padding:6px 8px">
                    <input class="input" name="carrier" placeholder="承运商" style="width:72px;font-size:12px;padding:6px 8px">
                    <button class="btn btn-primary btn-sm" type="submit">发货</button>
                  </form>
                @elseif ($d->status->value === 'shipped')
                  <span class="muted text-xs">
                    <span class="tag-dot">{{ $d->carrier ?? '承运中' }}</span> · {{ $d->shipped_at?->toDateString() }}
                  </span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="pager">
      {{ $deliveries->appends(request()->query())->links() }}
    </div>
  @endif
@endsection