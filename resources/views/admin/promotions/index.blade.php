@extends('layouts.dashboard')

@section('title', '促销管理')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">促销管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.promotions.create', ['tenant' => $tenant->slug]) }}">+ 新建促销</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  <section class="section" style="margin-bottom:32px">
    <div class="section-title"><span>活动</span></div>

    @php
      $typeLabels = [
        'new_customer' => '新客立减',
        'renewal'      => '续费抵用',
        'referral'     => '老带新',
        'upgrade'      => '升档抵扣',
        'festival'     => '节日满减',
      ];
    @endphp

    @if ($promotions->isEmpty())
      <div class="empty">
        <div class="empty-icon">🎉</div>
        <div>还没有促销活动。</div>
        <div class="empty-hint">点击「新建促销」创建活动,老带新由「我的推荐码」自动发券。</div>
      </div>
    @else
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>名称</th><th>类型</th><th>规则</th><th>库存</th><th>状态</th></tr>
          </thead>
          <tbody>
            @foreach ($promotions as $p)
              <tr>
                <td class="font-medium">{{ $p->name }}</td>
                <td><span class="tag tag-neutral">{{ $typeLabels[$p->type] ?? $p->type }}</span></td>
                <td class="mono text-xs">{{ json_encode($p->rule, JSON_UNESCAPED_UNICODE) }}</td>
                <td>{{ $p->stock ?? '不限' }}</td>
                <td>
                  <span class="tag {{ $p->status === 'active' ? 'tag-available' : 'tag-off' }}">
                    {{ $p->status === 'active' ? '进行中' : '已停用' }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <section class="section">
    <div class="section-title"><span>已发券</span></div>
    @if ($coupons->isEmpty())
      <div class="empty">
        <div class="empty-icon">🎟️</div>
        <div>还没有发券。</div>
      </div>
    @else
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>券码</th><th>用户</th><th>活动</th><th>状态</th><th>发放</th><th>使用</th></tr>
          </thead>
          <tbody>
            @foreach ($coupons as $c)
              <tr>
                <td class="mono">{{ $c->code ?? '—' }}</td>
                <td>{{ $c->user?->nickname ?? '—' }}</td>
                <td>{{ $c->promotion?->name ?? '—' }}</td>
                <td>
                  <span class="tag {{ $c->status === 'used' ? 'tag-available' : 'tag-off' }}">
                    {{ $c->status === 'used' ? '已使用' : '未使用' }}
                  </span>
                </td>
                <td class="text-xs">{{ $c->issued_at?->toDateString() }}</td>
                <td class="text-xs">{{ $c->used_at?->toDateString() ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>
@endsection