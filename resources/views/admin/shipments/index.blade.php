@extends('layouts.dashboard')

@section('title', '统一发货台')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">统一发货台</h1>
    <span class="note text-xs muted">聚合配送 + 礼盒两套出库队列</span>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <div class="section">
    <div class="section-title">
      <span>待发配送（{{ $pendingDeliveries->count() }}）</span>
      <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug]) }}">配送管理 ›</a>
    </div>

    @forelse ($pendingDeliveries as $d)
      <div class="sub-card mb-3">
        <div class="flex justify-between items-center">
          <span class="font-medium">{{ $d->adoption?->user?->nickname ?? '—' }} · {{ $d->harvest?->plot?->code ?? '—' }}</span>
          <span class="tag tag-warn">{{ $d->status->label() }}</span>
        </div>
        <div class="text-xs muted mt-1">
          {{ $d->adoption?->adoption_no }} · {{ $d->harvest?->season_year }} 年度
          · 收货:{{ $d->address ? $d->address->name.' · '.$d->address->phone : '—' }}
        </div>
        <form method="POST" action="{{ route('tenant.admin.shipments.delivery.ship', ['tenant' => $tenant->slug, 'delivery' => $d]) }}" class="flex gap-1 mt-2">
          @csrf
          <input class="input" name="tracking_no" required maxlength="80" placeholder="运单号">
          <input class="input" name="carrier" maxlength="40" placeholder="承运(顺丰等)" style="max-width:140px">
          <button class="btn btn-primary btn-sm" type="submit">发货</button>
        </form>
      </div>
    @empty
      <p class="note text-xs">暂无待发配送单。</p>
    @endforelse
  </div>

  <div class="section" style="margin-top:28px">
    <div class="section-title">
      <span>待发礼盒（{{ $pendingGifts->count() }}）</span>
      <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.gift-boxes.index', ['tenant' => $tenant->slug]) }}">礼盒管理 ›</a>
    </div>

    @forelse ($pendingGifts as $g)
      <div class="sub-card mb-3">
        <div class="flex justify-between items-center">
          <span class="font-medium">{{ $g->festival->label() }}礼盒 · {{ $g->adoption?->user?->nickname ?? '—' }}</span>
          <span class="tag {{ $g->status->value === 'making' ? 'tag-renew' : 'tag-warn' }}">{{ $g->status->label() }}</span>
        </div>
        <div class="text-xs muted mt-1">
          {{ $g->code }} · 收礼:{{ $g->recipient_name ? $g->recipient_name.' · '.$g->recipient_phone : '（未定制收礼人）' }}
        </div>
        <div class="flex gap-1 mt-2" style="flex-wrap:wrap">
          @if ($g->status->value === 'draft')
            <form method="POST" action="{{ route('tenant.admin.shipments.gift.making', ['tenant' => $tenant->slug, 'giftBox' => $g]) }}">
              @csrf
              <button class="btn btn-ghost btn-sm" type="submit">开始制作</button>
            </form>
          @endif
          <form method="POST" action="{{ route('tenant.admin.shipments.gift.ship', ['tenant' => $tenant->slug, 'giftBox' => $g]) }}" class="flex gap-1">
            @csrf
            <input class="input" name="tracking_no" required maxlength="80" placeholder="运单号">
            <input class="input" name="carrier" maxlength="40" placeholder="承运" style="max-width:120px">
            <button class="btn btn-primary btn-sm" type="submit">发货</button>
          </form>
        </div>
      </div>
    @empty
      <p class="note text-xs">暂无待发礼盒。</p>
    @endforelse
  </div>
@endsection