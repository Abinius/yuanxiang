@extends('layouts.dashboard')

@section('title', '离线开单')

@section('nav_right')
  <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}">管理后台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenant->slug]) }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  <div class="page-header">
    <h1 class="page-title">离线开单</h1>
    <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}">返回订单列表</a>
  </div>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('tenant.admin.adoptions.store', ['tenant' => $tenant->slug]) }}">
    @csrf
    <div class="field">
      <label>云乡民手机号（须已注册）</label>
      <input class="input" name="phone" required pattern="1\d{10}" value="{{ old('phone') }}" placeholder="13800000000">
    </div>

    <div class="field">
      <label>田块</label>
      <select class="select" name="plot_id" required>
        <option value="">（选择可认养田块）</option>
        @forelse ($plots as $plot)
          <option value="{{ $plot->id }}" @selected(old('plot_id') == $plot->id)>
            {{ $plot->code }} · ¥{{ number_format($plot->price_yearly) }}/年
          </option>
        @empty
          <option value="">暂无可认养田块</option>
        @endforelse
      </select>
    </div>

    <div class="field">
      <label>年度</label>
      <input class="input" type="number" name="season_year" min="2000" max="2100" value="{{ old('season_year', now()->year) }}">
    </div>

    <div class="field">
      <label>铭牌命名（可选，线下收款后直接生效）</label>
      <input class="input" name="named_label" maxlength="30" value="{{ old('named_label') }}" placeholder="例:阿林的光彩田">
    </div>

    <p class="note text-xs" style="margin-bottom:14px">
      线下已收款则勾选命名后直接生效；不命名则生成"待签约"单，由云乡民在 /my 完成签署。
    </p>

    <button class="btn btn-primary" type="submit" style="width:100%">创建离线订单（已收款）</button>
  </form>
@endsection