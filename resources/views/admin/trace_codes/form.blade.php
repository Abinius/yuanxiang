@extends('layouts.dashboard')

@section('title', '生成溯源码')

@section('nav_right')
  <a href="{{ route('tenant.admin.trace-codes.index', ['tenant' => $tenant->slug]) }}">溯源码列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">生成溯源码</h1>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('tenant.admin.trace-codes.store', ['tenant' => $tenant->slug]) }}">
      @csrf
      <div class="field">
        <label>采收</label>
        <select name="harvest_id" class="select" required>
          @forelse ($harvests as $h)
            <option value="{{ $h->id }}" @selected(old('harvest_id') == $h->id)>
              {{ $h->plot?->code ?? '未绑定田块' }} · {{ $h->season_year }} 年度 · {{ $h->harvested_at?->toDateString() }}
            </option>
          @empty
            <option value="">暂无采收记录,请先在家人端录入</option>
          @endforelse
        </select>
      </div>

      <div class="field">
        <label>生成数量(箱)</label>
        <input class="input" type="number" name="count" min="1" max="50" value="{{ old('count', 1) }}" required>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">生成并跳转打印</button>
    </form>
    <p class="note text-xs" style="margin-top:14px">
      每箱生成唯一溯源码;二维码在打印页由浏览器生成后打印标签。
    </p>
  </div>
@endsection