@extends('layouts.site')

@section('title', '认养订单')

@section('content')
  <div class="panel" style="max-width:520px;margin:0 auto">
    @if ($adoption->status->value === 'active')
      <div class="ok mb-4">认养协议已签署 · 生效中</div>

      @include('site.partials.nameplate', ['adoption' => $adoption])

      <div class="rule-block">
        <div class="rule-row"><span class="rule-key">订单号</span><span>{{ $adoption->adoption_no }}</span></div>
        <div class="rule-row"><span class="rule-key">认养期</span><span>{{ $adoption->start_date->format('Y-m-d') }} ～ {{ $adoption->end_date?->format('Y-m-d') }}</span></div>
        <div class="rule-row"><span class="rule-key">签署</span><span>{{ $adoption->agreement_signed_at?->format('Y-m-d H:i') }}</span></div>
      </div>

      <div class="flex gap-2" style="margin-top:16px;flex-wrap:wrap">
        <a class="btn btn-primary btn-lg" href="{{ route('tenant.my.plot', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">进入我的田</a>
        <a class="btn btn-ghost btn-lg" href="{{ route('tenant.adopt.index', ['tenant' => $tenant->slug]) }}">继续逛田</a>
      </div>

    @elseif ($adoption->status->value === 'pending_agreement')
      <div class="ok mb-4">支付成功!</div>
      <h1 style="font-size:var(--ds-h2);margin-bottom:4px">{{ $adoption->adoptable->code }} · 待签署</h1>
      <p class="lede">请签署认养协议并给田块命名</p>

      <div class="rule-block">
        <div class="rule-row"><span class="rule-key">订单号</span><span>{{ $adoption->adoption_no }}</span></div>
        <div class="rule-row"><span class="rule-key">年度</span><span>{{ $adoption->season_year }} · ¥{{ number_format($adoption->annual_fee) }}</span></div>
      </div>

      @if ($errors->any())
        <div class="alert mt-3">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('tenant.adopt.sign', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}" style="margin-top:16px">
        @csrf
        <div class="field">
          <label>给这块田起个名字(铭牌 / 乡民卡用)</label>
          <input class="input" name="named_label" required maxlength="30" placeholder="例:阿林的光彩田" value="{{ old('named_label') }}">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">签署协议并命名</button>
      </form>
      <p class="note text-xs" style="margin-top:14px">
        签署即视为同意认养协议(丰欠共担 / 保底条款);开发期模拟,正式协议文案随法务定稿。
      </p>

    @else
      <div class="alert">订单状态:{{ $adoption->status->value }}</div>
      <a class="btn btn-ghost btn-block btn-lg" href="{{ route('tenant.adopt.index', ['tenant' => $tenant->slug]) }}">返回认养</a>
    @endif
  </div>
@endsection