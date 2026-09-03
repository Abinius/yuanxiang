@extends('layouts.dashboard')

@section('title', '佣金与提现')

@section('nav_right')
  <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}">看板</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <div class="page-header">
    <h1 class="page-title">佣金与提现</h1>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-4">佣金率/冷却期在「设置 → 分销佣金」配置（≤10%）。佣金按认养实际结算，冷却期后转可提现。</p>

  <div class="card-grid grid-4" style="margin-bottom:18px">
    <div class="card" style="padding:12px"><div class="text-xs muted">待转正(pending)</div><div class="font-medium">¥{{ number_format($summary['pending'], 2) }}</div></div>
    <div class="card" style="padding:12px"><div class="text-xs muted">可提现(available)</div><div class="font-medium text-brand">¥{{ number_format($summary['available'], 2) }}</div></div>
    <div class="card" style="padding:12px"><div class="text-xs muted">已提现(settled)</div><div class="font-medium">¥{{ number_format($summary['settled'], 2) }}</div></div>
    <div class="card" style="padding:12px"><div class="text-xs muted">冻结(frozen)</div><div class="font-medium">¥{{ number_format($summary['frozen'], 2) }}</div></div>
  </div>

  <h2 class="mb-3" style="font-size:var(--ds-h3)">提现申请（待审）</h2>
  @forelse ($payouts->where('status', 'pending') as $p)
    <div class="sub-card mb-2" style="padding:12px 14px">
      <div class="flex justify-between items-center">
        <div>
          <div class="text-sm">{{ $p->user?->nickname }} 申请提现 <b class="text-brand">¥{{ number_format($p->amount, 2) }}</b></div>
          <div class="text-xs muted mt-1">{{ $p->created_at->format('Y-m-d H:i') }}</div>
        </div>
        <div class="flex gap-2">
          <form method="POST" action="{{ route('tenant.admin.commissions.approve', ['tenant' => $tenant->slug, 'payout' => $p]) }}">
            @csrf
            <button class="btn btn-primary btn-sm" type="submit">发放</button>
          </form>
          <form method="POST" action="{{ route('tenant.admin.commissions.reject', ['tenant' => $tenant->slug, 'payout' => $p]) }}" onsubmit="return confirm('驳回该提现？')">
            @csrf
            <button class="btn btn-ghost btn-sm" type="submit">驳回</button>
          </form>
        </div>
      </div>
    </div>
  @empty
    <p class="note text-sm">暂无待审提现。</p>
  @endforelse

  <h2 class="mb-3 mt-5" style="font-size:var(--ds-h3)">佣金明细</h2>
  <table class="table">
    <thead>
      <tr><th>推荐人</th><th>认养单</th><th>tier</th><th>率</th><th>金额</th><th>状态</th><th>时间</th></tr>
    </thead>
    <tbody>
      @forelse ($items as $i)
        <tr>
          <td>{{ $i->user?->nickname }}</td>
          <td class="text-xs muted">{{ $i->adoption?->adoption_no }}</td>
          <td>{{ $i->tier }}</td>
          <td>{{ $i->rate }}%</td>
          <td>¥{{ number_format($i->amount, 2) }}</td>
          <td>{{ $i->status }}</td>
          <td class="text-xs muted">{{ $i->created_at->format('Y-m-d') }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-xs muted">暂无佣金记录。</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
