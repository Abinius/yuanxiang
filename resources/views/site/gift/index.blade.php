@extends('layouts.site')

@section('title', '节日礼盒')

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">节日礼盒 · {{ $adoption->named_label }}</h1>
      <a class="back-link" href="{{ route('tenant.my.plot', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">返回我的田</a>
    </div>

    @if (session('ok'))
      <div class="ok">{{ session('ok') }}</div>
    @endif

    <p class="note text-xs">
      用你的认养权益为亲友定制节日礼盒:春节 / 端午 / 中秋各 1 盒,含亲笔签贺卡。
    </p>

    @forelse ($giftBoxes as $g)
      <div class="sub-card mb-3">
        <div class="flex justify-between items-center">
          <span class="tag" style="background:var(--color-brand-50);color:var(--color-brand-500)">{{ $g->festival->label() }} {{ $g->year }}</span>
          <span class="text-xs muted">{{ $g->status->label() }}</span>
        </div>
        <div class="text-sm mt-2" style="color:var(--ds-text-soft);line-height:1.8">
          收礼人:{{ $g->recipient_name ?? '未填写' }}{{ $g->recipient_phone ? '('.$g->recipient_phone.')' : '' }}<br>
          @if ($g->message) 寄语:{{ $g->message }}<br>@endif
          @if ($g->tracking_no) 运单:{{ $g->tracking_no }}({{ $g->carrier ?? '承运中' }})@endif
        </div>
        @if ($g->status->value === 'draft')
          <div class="mt-2">
            <a class="btn btn-primary btn-sm" href="{{ route('tenant.my.gift.customize', ['tenant' => $tenant->slug, 'adoption' => $adoption, 'giftBox' => $g]) }}">去定制</a>
          </div>
        @endif
      </div>
    @empty
      <div class="empty mt-4">
        <div class="empty-icon">🎁</div>
        <div>还没有礼盒。</div>
      </div>
    @endforelse

    <div class="text-center mt-4">
      <a class="btn btn-primary btn-lg" href="{{ route('tenant.my.gift.create', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">+ 定制礼盒</a>
    </div>
  </div>
@endsection