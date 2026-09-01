@extends('layouts.dashboard')

@section('title', '录入采收')

@section('nav_right')
  <a href="{{ route('tenant.family.dashboard', ['tenant' => $tenant->slug]) }}" style="margin-right:16px">家人端</a>
  @if (auth()->user()->role->value === 'tenant_admin')
    <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}" style="margin-right:16px">管理后台</a>
  @endif
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}" style="margin-right:16px">前台</a>
  <span class="muted">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenant->slug]) }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  <h2>录入采收</h2>
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif
  <form method="POST" action="{{ route('tenant.family.harvest.store', ['tenant' => $tenant->slug]) }}">
    @csrf
    <label>田块</label>
    <select name="plot_id" required>
      @foreach ($plots as $plot)
        <option value="{{ $plot->id }}" @selected(old('plot_id') == $plot->id)>{{ $plot->code }}</option>
      @endforeach
    </select>

    <label>采收年度</label>
    <input type="number" name="season_year" min="2000" max="2100" value="{{ old('season_year', now()->year) }}">

    <label>采收日期</label>
    <input type="date" name="harvested_at" required value="{{ old('harvested_at') }}">

    <label>干重（kg）</label>
    <input type="number" step="0.01" name="dry_weight_kg" required value="{{ old('dry_weight_kg') }}">

    <label>品质等级（可选）</label>
    <input name="quality_grade" maxlength="20" value="{{ old('quality_grade') }}">

    <label>备注</label>
    <textarea name="notes" rows="3" maxlength="500">{{ old('notes') }}</textarea>

    <button class="btn" type="submit" style="width:100%;margin-top:16px">保存采收</button>
  </form>
@endsection
