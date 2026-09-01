@extends('layouts.site')

@section('title', $plot->code.' · 认养')

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="flex items-center justify-between mb-2">
      <h1 style="font-size:var(--ds-h2);margin:0">{{ $plot->code }}</h1>
      <span class="tag {{ $plot->status->value === 'available' ? 'tag-available' : ($plot->status->value === 'adopted' ? 'tag-adopted' : 'tag-off') }}">
        {{ $statusLabels[$plot->status->value] }}
      </span>
    </div>
    <p class="lede mb-4">
      {{ $plot->type->value === 'plant' ? '单株认养 · 拼团田池均摊' : $plot->mu_area.' 亩 · 分地档' }}
      · <b class="text-brand serif">¥{{ number_format($plot->price_yearly) }}/年</b>
    </p>

    <div class="rule-block">
      <div class="rule-row">
        <span class="rule-key">交付</span>
        <span>{{ $plot->type->value === 'plant' ? '保底 0.5kg/株/年(池均摊)' : '保底 15kg 干果/年 · 丰欠共担' }}</span>
      </div>
      <div class="rule-row">
        <span class="rule-key">权益</span>
        <span>命名 / 监控直播 / 溯源 / 三节礼盒配额 / 乡民卡</span>
      </div>
      <div class="rule-row">
        <span class="rule-key">溯源</span>
        <span>有机肥(NXLB)批次全程可查</span>
      </div>
    </div>

    <div class="mb-4">
      <span class="serif text-brand font-medium">溯源</span>
      <a style="font-size:var(--ds-body-s);color:var(--color-brand-500);font-weight:600"
         href="{{ route('tenant.trace.show', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">
        查看溯源时间线 ›
      </a>
    </div>

    @if ($plot->status->value === 'available')
      <details class="details">
        <summary>立即认养(¥{{ number_format($plot->price_yearly) }}/年)</summary>
        @auth
          <form method="POST" action="{{ route('tenant.adopt.order', ['tenant' => $tenant->slug, 'plot' => $plot]) }}" style="margin-top:14px">
            @csrf
            @if ($errors->any())
              <div class="alert">{{ $errors->first() }}</div>
            @endif
            <div class="card-grid grid-2">
              <div class="field">
                <label>收货人</label>
                <input class="input" name="name" required>
              </div>
              <div class="field">
                <label>手机号</label>
                <input class="input" name="phone" required pattern="1\d{10}">
              </div>
            </div>
            <div class="card-grid grid-2">
              <div class="field">
                <label>省</label>
                <input class="input" name="province">
              </div>
              <div class="field">
                <label>市</label>
                <input class="input" name="city">
              </div>
            </div>
            <div class="field">
              <label>详细地址</label>
              <input class="input" name="detail" required>
            </div>
            <div class="field">
              <label>推荐码(可选)</label>
              <input class="input" name="referral_code" maxlength="40" value="{{ old('referral_code') }}">
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">提交订单</button>
          </form>
        @else
          <p class="alert mt-3" style="margin:14px 0 0">
            请先<a href="{{ route('tenant.login', ['tenant' => $tenant->slug]) }}">登录</a>后再认养
          </p>
        @endauth
      </details>
    @else
      <p class="alert mt-4">该田块当前不可认养</p>
    @endif
  </div>
@endsection