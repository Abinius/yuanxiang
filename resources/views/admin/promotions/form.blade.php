@extends('layouts.dashboard')

@section('title', '新建促销')

@section('nav_right')
  <a href="{{ route('tenant.admin.promotions.index', ['tenant' => $tenant->slug]) }}">促销列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">新建促销</h1>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('tenant.admin.promotions.store', ['tenant' => $tenant->slug]) }}">
      @csrf
      <div class="field">
        <label>名称</label>
        <input class="input" name="name" required maxlength="60" value="{{ old('name') }}">
      </div>

      <div class="field">
        <label>类型</label>
        <select name="type" class="select" required>
          @foreach (['new_customer' => '新客立减', 'renewal' => '续费抵用', 'referral' => '老带新', 'upgrade' => '升档抵扣', 'festival' => '节日满减'] as $value => $label)
            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="card-grid grid-2">
        <div class="field">
          <label>减免金额(元)</label>
          <input class="input" type="number" name="amount" step="0.01" min="0" value="{{ old('amount') }}">
        </div>
        <div class="field">
          <label>或折扣比例(0-1,如 0.1=9折)</label>
          <input class="input" type="number" name="percent" step="0.01" min="0" max="1" value="{{ old('percent') }}">
        </div>
      </div>

      <div class="field">
        <label>库存(可选,留空不限)</label>
        <input class="input" type="number" name="stock" min="0" value="{{ old('stock') }}">
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">创建</button>
    </form>
    <p class="note text-xs" style="margin-top:14px">
      老带新活动由「我的推荐码」自动发券;新客立减/续费抵用券在用户使用时抵扣年费。
    </p>
  </div>
@endsection