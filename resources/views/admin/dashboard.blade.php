@extends('layouts.dashboard')

@section('title', '商户后台')

@section('content')
  <div class="hero-bar">
    <div>
      <h1 class="hero-title">{{ auth()->user()->nickname }}，欢迎回来</h1>
      <p class="lede">{{ $tenant->name }} · 经营看板概览</p>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><div class="num sm">{{ $stats['conversion'] }}%</div><div class="label">认养转化率</div></div>
      <div class="hero-stat"><div class="num sm">{{ $stats['attainment'] === null ? '—' : $stats['attainment'].'%' }}</div><div class="label">产出达标率</div></div>
      <div class="hero-stat"><div class="num sm">{{ $stats['traceRate'] === null ? '—' : $stats['traceRate'].'%' }}</div><div class="label">溯源查看率</div></div>
      <div class="hero-stat"><div class="num sm">{{ $stats['renewalIntent'] }}</div><div class="label">续费意向</div></div>
    </div>
  </div>

  <section class="section">
    <div class="section-title">
      <span>运营数据</span>
      <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}">查看全部订单</a>
    </div>
    <div class="card-grid grid-6">
      <a class="card card-link" href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $adoptionCount }}</div>
        <div class="label">认养订单</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $pendingPaymentCount }}</div>
        <div class="label">待支付</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $activeAdoptions }}</div>
        <div class="label">生效中</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.farm-logs.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $farmLogCount }}</div>
        <div class="label">农事记录</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $deliveryCount }}</div>
        <div class="label">配送单</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.adjustments.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $adjustmentCount }}</div>
        <div class="label">待补退</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.gift-boxes.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $giftBoxCount }}</div>
        <div class="label">礼盒</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.cameras.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $cameraCount }}</div>
        <div class="label">摄像头</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.short-links.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">{{ $shortLinkCount }}</div>
        <div class="label">短链接</div>
      </a>
      <a class="card card-link" href="{{ route('tenant.admin.trace-codes.index', ['tenant' => $tenant->slug]) }}">
        <div class="num">—</div>
        <div class="label">溯源码</div>
      </a>
      <a class="card card-link card-soft" href="{{ route('tenant.admin.settings.edit', ['tenant' => $tenant->slug]) }}">
        <div class="num sm" style="color:var(--ds-text-soft)">⚙</div>
        <div class="label">站点设置</div>
      </a>
      <a class="card card-link card-soft" href="{{ route('tenant.admin.promotions.index', ['tenant' => $tenant->slug]) }}">
        <div class="num sm" style="color:var(--ds-text-soft)">%</div>
        <div class="label">促销</div>
      </a>
    </div>
  </section>

  <section class="section">
    <div class="section-title">年度数据</div>
    <p class="note text-xs mb-4">
      转化率 = 已生效(含到期)/全部订单；产出达标率 = 本年度采收总量 / Σ各单保底；溯源查看率 = 被扫码箱数 / 总箱数。
    </p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>年度</th><th>认养单</th><th>生效</th><th>采收量(kg)</th></tr>
        </thead>
        <tbody>
          @forelse ($seasonStats as $s)
            <tr>
              <td>{{ $s['year'] }}</td>
              <td>{{ $s['count'] }}</td>
              <td>{{ $s['active'] }}</td>
              <td>{{ $s['harvest_kg'] }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty"><span class="empty-hint">暂无年度数据</span></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection