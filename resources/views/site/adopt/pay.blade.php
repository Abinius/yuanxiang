@extends('layouts.site')

@section('title', '订单确认')

@section('content')
  <div class="panel" style="max-width:520px;margin:0 auto">
    <h1 style="font-size:var(--ds-h2);margin:0 0 16px">订单确认</h1>

    <div class="rule-block">
      <div class="rule-row"><span class="rule-key">订单号</span><span>{{ $adoption->adoption_no }}</span></div>
      <div class="rule-row"><span class="rule-key">田块</span><span>{{ $adoption->adoptable->code }}({{ $adoption->adoptable->type }})</span></div>
      <div class="rule-row"><span class="rule-key">年度</span><span>{{ $adoption->season_year }} · <b class="text-brand">¥{{ number_format($adoption->annual_fee) }}</b></span></div>
    </div>

    @if ($adoption->status->value === 'pending_payment')
      <p class="text-sm" style="color:var(--ds-text-mute);margin-bottom:16px;line-height:1.7">
        @if (! config('wechat.mock') && filled($request->user()->openid))
          微信支付(商户主体:花乌巷食品)
        @else
          <span class="text-warn font-medium">⚠️ 开发期模拟支付</span>
          真接需微信客户端登录 + 商户凭证(P1)。
        @endif
      </p>
      @if (! config('wechat.mock') && filled($request->user()->openid))
        <button class="btn btn-primary btn-block btn-lg" id="wx-pay" type="button">微信支付 ¥{{ number_format($adoption->annual_fee) }}</button>
      @else
        <form method="POST" action="{{ route('tenant.adopt.confirm-pay', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">
          @csrf
          <button class="btn btn-soft btn-block btn-lg" type="submit">模拟支付成功</button>
        </form>
      @endif

    @elseif ($adoption->status->value === 'pending_agreement')
      <div class="ok mb-4">订单已支付成功</div>
      <p class="text-sm" style="color:var(--ds-text-mute);margin-bottom:16px">下一步签署认养协议即可生效。</p>
      <a class="btn btn-primary btn-block btn-lg" href="{{ route('tenant.adopt.success', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">去签署协议</a>

    @elseif ($adoption->status->value === 'active')
      <div class="ok mb-4">认养已生效</div>
      <p class="text-sm" style="color:var(--ds-text-mute);margin-bottom:16px">欢迎进入你的田。</p>
      <a class="btn btn-primary btn-block btn-lg" href="{{ route('tenant.my.plot', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">进入我的田</a>

    @else
      <p class="text-sm" style="color:var(--ds-text-mute);margin-bottom:16px">该订单已取消或结束。</p>
      <a class="btn btn-ghost btn-block btn-lg" href="{{ route('tenant.my.index', ['tenant' => $tenant->slug]) }}">返回我的认养</a>
    @endif
  </div>

  @if (! config('wechat.mock') && filled($request->user()->openid) && $adoption->status->value === 'pending_payment')
    <script>
    (function () {
      var btn = document.getElementById('wx-pay');
      if (!btn) return;
      btn.addEventListener('click', function () {
        if (!window.WeixinJSBridge) { alert('请在微信客户端内打开'); return; }
        btn.disabled = true;
        fetch('/t/{{ $tenant->slug }}/adopt/order/{{ $adoption->id }}/wechat-pay', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
          body: '{}'
        })
        .then(function (r) { return r.json(); })
        .then(function (params) {
          WeixinJSBridge.invoke('getBrandWCPayRequest', params, function (res) {
            if (res.err_msg === 'getBrandWCPayRequest:ok') {
              location.href = '/t/{{ $tenant->slug }}/adopt/order/{{ $adoption->id }}/success';
            } else { alert('支付未完成'); btn.disabled = false; }
          });
        })
        .catch(function () { alert('拉起支付失败,请重试'); btn.disabled = false; });
      });
    })();
    </script>
  @endif
@endsection