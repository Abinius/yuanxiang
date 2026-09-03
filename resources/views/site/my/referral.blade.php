@extends('layouts.site')

@section('title', '我的分销')

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">我的分销</h1>
      <a class="back-link" href="{{ route('tenant.my.index', ['tenant' => $tenant->slug]) }}">我的认养</a>
    </div>

    @if (session('ok'))
      <div class="ok">{{ session('ok') }}</div>
    @endif
    @if (session('error'))
      <div class="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert">{{ $errors->first() }}</div>
    @endif

    @if ($coupon)
      @php $reward = (float) ($coupon->promotion?->rule['amount'] ?? 0); @endphp
      <div class="sub-card" style="padding:16px;margin-bottom:18px;text-align:center">
        <p class="text-sm" style="color:var(--ds-text-mute);line-height:1.7;margin:0 0 8px">
          好友用你的推荐码认养,你和好友各得
          <b class="text-brand">{{ $reward > 0 ? '¥'.number_format($reward).' 优惠券' : '一张优惠券' }}</b>，
          你另按会员等级拿<b class="text-brand">{{ $commission['rate'] }}%</b>佣金。
        </p>
        <div class="mono font-bold text-brand" style="font-size:26px;letter-spacing:2px">{{ $coupon->code }}</div>
        <button id="ref-copy-btn" class="btn btn-primary btn-sm mt-2" type="button"
                onclick="(function(b){navigator.clipboard.writeText('{{ $coupon->code }}').then(function(){b.textContent='已复制'}).catch(function(){});})(this)">
          复制推荐码
        </button>
      </div>
    @endif

    <h2 style="margin:18px 0 8px;font-size:var(--ds-h3)">佣金账户</h2>
    <div class="card-grid grid-4" style="margin-bottom:14px">
      <div class="card" style="padding:12px"><div class="text-xs muted">会员等级</div><div class="font-medium text-brand">{{ $commission['tier'] === 'partner' ? '合伙人' : ($commission['tier'] === 'expert' ? '达人' : '红人') }}</div></div>
      <div class="card" style="padding:12px"><div class="text-xs muted">佣金率</div><div class="font-medium">{{ $commission['rate'] }}%</div></div>
      <div class="card" style="padding:12px"><div class="text-xs muted">可提现</div><div class="font-medium text-brand serif" style="font-size:1.2rem">¥{{ number_format($commission['available'], 2) }}</div></div>
      <div class="card" style="padding:12px"><div class="text-xs muted">待转正/冻结/已提</div><div class="text-sm">¥{{ number_format($commission['pending'], 2) }} / ¥{{ number_format($commission['frozen'], 2) }} / ¥{{ number_format($commission['settled'], 2) }}</div></div>
    </div>

    @if ($commission['available'] > 0)
      <form method="POST" action="{{ route('tenant.my.commission.cash-out', ['tenant' => $tenant->slug]) }}" class="flex gap-2 items-end mb-4">
        @csrf
        <div class="field flex-1">
          <label>提现金额(元)</label>
          <input class="input" type="number" step="0.01" min="1" max="{{ $commission['available'] }}" name="amount" required>
        </div>
        <button class="btn btn-primary" type="submit">申请提现</button>
      </form>
    @endif

    @if ($commission['items']->isNotEmpty())
      <h2 style="margin:18px 0 8px;font-size:var(--ds-h3)">佣金流水</h2>
      @foreach ($commission['items'] as $item)
        <div class="sub-card mb-2" style="padding:12px 14px">
          <div class="flex justify-between">
            <span class="text-sm">{{ $item->adoption?->adoption_no ?? '认养' }}</span>
            <span class="font-medium {{ $item->status === 'frozen' ? 'text-xs muted' : '' }}">¥{{ number_format($item->amount, 2) }}</span>
          </div>
          <div class="text-xs muted mt-1">tier {{ $item->tier }} · {{ $item->rate }}% · {{ $item->status }} · {{ $item->created_at->format('Y-m-d') }}</div>
        </div>
      @endforeach
    @endif

    <h2 style="margin:18px 0 8px;font-size:var(--ds-h3)">推荐业绩</h2>
    <div class="text-sm muted mb-2">累计推荐 <b class="text-brand">{{ $stats['total'] }}</b> 人 · 本年 {{ $stats['this_year'] }} 人 · 带动 ¥{{ number_format($stats['revenue']) }}</div>
    @forelse ($stats['recent'] as $a)
      <div class="sub-card mb-2" style="padding:12px 14px">
        <div class="flex justify-between text-sm">
          <span>{{ $a->user?->nickname }} 认养 {{ $a->adoptable?->code ?? '' }}</span>
          <span class="muted">¥{{ number_format($a->annual_fee) }} · {{ $a->created_at->format('Y-m-d') }}</span>
        </div>
      </div>
    @empty
      <p class="note text-xs">还没有成功推荐的认养。</p>
    @endforelse
  </div>
@endsection
