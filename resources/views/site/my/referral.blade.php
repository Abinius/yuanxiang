@extends('layouts.site')

@section('title', '我的推荐码')

@section('content')
  <div class="panel" style="max-width:560px;margin:0 auto;text-align:center">
    <div class="page-header">
      <h1 class="page-title">我的推荐码</h1>
      <a class="back-link" href="{{ route('tenant.my.index', ['tenant' => $tenant->slug]) }}">我的认养</a>
    </div>

    @if ($coupon)
      @php $reward = (float) ($coupon->promotion?->rule['amount'] ?? 0); @endphp
      <p class="text-sm" style="color:var(--ds-text-mute);line-height:1.7">
        好友用你的推荐码认养,你和好友各得
        <b class="text-brand">{{ $reward > 0 ? '¥'.number_format($reward).' 优惠券' : '一张优惠券' }}</b>。
      </p>

      <div class="mono font-bold text-brand" style="font-size:26px;letter-spacing:2px;margin:18px 0">
        {{ $coupon->code }}
      </div>

      <button class="btn btn-primary btn-sm" type="button"
              onclick="navigator.clipboard.writeText('{{ $coupon->code }}').then(function(){this.textContent='已复制'}).catch(function(){})">
        复制推荐码
      </button>

      <p class="note text-xs" style="margin-top:14px">好友下单时在认养页填写该码即可。</p>

      <div style="margin-top:16px">
        @include('site.partials.share', ['shareUrl' => route('tenant.adopt.index', ['tenant' => $tenant->slug]), 'shareTitle' => '认养一块宁夏枸杞田'])
      </div>
    @else
      <div class="empty mt-4">
        <div class="empty-icon">🎟️</div>
        <div>推荐活动暂未开启,敬请期待。</div>
      </div>
    @endif
  </div>
@endsection