@extends('layouts.dashboard')

@section('title', '打单 · 生成配送单')

@section('nav_right')
  <a href="{{ route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug]) }}">配送列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">打单 · 按采收生成配送单</h1>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('tenant.admin.deliveries.store', ['tenant' => $tenant->slug]) }}">
      @csrf
      <div class="field">
        <label>采收</label>
        <select name="harvest_id" class="select" required>
          @forelse ($harvests as $h)
            <option value="{{ $h->id }}" @selected(old('harvest_id') == $h->id)>
              {{ $h->plot?->code ?? '未绑定田块' }} · {{ $h->season_year }} 年度 · {{ $h->harvested_at?->toDateString() }} · {{ $h->dry_weight_kg }}kg
            </option>
          @empty
            <option value="">暂无采收记录,请先在家人端录入</option>
          @endforelse
        </select>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">生成配送单</button>
    </form>
    <p class="note text-xs" style="margin-top:14px">
      为该田块的 active 认养人生成待发货配送单(跳过已生成者);地址取认养人默认/最新。
    </p>
  </div>
@endsection