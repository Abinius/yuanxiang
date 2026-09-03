@extends('layouts.site')

@section('title', '我的认养')

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">我的认养</h1>
      <a class="back-link" href="{{ route('tenant.my.referral', ['tenant' => $tenant->slug]) }}">我的推荐码 ›</a>
    </div>

    @if (session('ok'))
      <div class="ok">{{ session('ok') }}</div>
    @endif

    <hr class="divider">

    @forelse ($adoptions as $adoption)
      @php
        $st = $adoption->status->value;
        $tagClass = match ($st) {
          'active'            => 'tag-adopted',
          'pending_payment'   => 'tag-warn',
          'pending_agreement' => 'tag-renew',
          'ended','cancelled' => 'tag-sold',
          default             => 'tag-off',
        };
      @endphp

      <div class="adoption-card">
        <div class="ad-head">
          <div>
            @if ($st === 'active')
              <a class="ad-title"
                 href="{{ route('tenant.my.plot', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">
                {{ $adoption->named_label ?: '未命名' }} ›
              </a>
            @else
              <div class="ad-title">{{ $adoption->named_label ?: '未命名' }}</div>
            @endif
            <div class="ad-meta">
              {{ $adoption->adoptable?->code ?? '—' }} · {{ $adoption->season_year }} 年度 · ¥{{ number_format($adoption->annual_fee) }}
            </div>
          </div>
          <span class="tag {{ $tagClass }}">{{ $adoption->status->label() }}</span>
        </div>

        <div class="ad-actions">
          @if ($st === 'active')
            <form method="POST" action="{{ route('tenant.my.renew', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">
              @csrf
              <button class="btn btn-primary btn-sm" type="submit">续费下一季</button>
            </form>
            <form method="POST" action="{{ route('tenant.my.auto-renew', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">
              @csrf
              <label class="muted" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" name="auto_renew" value="1" @checked($adoption->auto_renew) onchange="this.form.submit()">
                续费意向
              </label>
            </form>
            @if ($adoption->daysRemaining() !== null)
              <span class="note text-xs">
                距到期 <b class="text-brand">{{ $adoption->daysRemaining() }}</b> 天
                @if ($adoption->daysRemaining() <= 30) <span class="text-warn">· 可续费</span> @endif
              </span>
            @endif
          @elseif ($st === 'ended')
            <form method="POST" action="{{ route('tenant.my.renew', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">
              @csrf
              <button class="btn btn-primary btn-sm" type="submit">续费下一季</button>
            </form>
          @elseif ($st === 'pending_payment')
            @php $resume = $adoptionService->resumePayment($adoption); @endphp
            @if ($resume)
              <a class="btn btn-primary btn-sm" href="{{ route('tenant.adopt.pay', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">继续支付</a>
              <span class="note text-xs">订单 {{ $adoptionService->expiresAt($adoption)->format('Y-m-d H:i') }} 前支付有效</span>
            @else
              <span class="note text-xs text-warn">订单已过期，请重新下单</span>
            @endif
          @elseif ($st === 'pending_agreement')
            <a class="btn btn-primary btn-sm" href="{{ route('tenant.adopt.success', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">去签署协议</a>
            <span class="note text-xs">已支付，签署命名后正式生效。</span>
          @endif
        </div>
      </div>
    @empty
      <div class="empty">
        <div class="empty-icon">🌱</div>
        <div>还没有认养,去看看田。</div>
        <a class="btn btn-primary mt-3" href="{{ route('tenant.adopt.index', ['tenant' => $tenant->slug]) }}">去认养一块田</a>
      </div>
    @endforelse
  </div>
@endsection