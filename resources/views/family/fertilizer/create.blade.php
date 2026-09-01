@extends('layouts.dashboard')

@section('title', '录入有机肥批次')

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
  <h2>录入有机肥批次</h2>
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif
  <form method="POST" action="{{ route('tenant.family.fertilizer.store', ['tenant' => $tenant->slug]) }}">
    @csrf
    <label>批次号</label>
    <input name="batch_no" required maxlength="60" value="{{ old('batch_no') }}">

    <label>生产日期</label>
    <input type="date" name="produced_at" required value="{{ old('produced_at') }}">

    <label>NXLB 参考编号</label>
    <input name="nxlb_ref" maxlength="120" value="{{ old('nxlb_ref') }}">

    <label>配料 / 成分</label>
    <textarea name="ingredients" rows="3" maxlength="1000">{{ old('ingredients') }}</textarea>

    <label>检测报告链接（可选）</label>
    <input name="test_report_url" maxlength="500" value="{{ old('test_report_url') }}">

    <button class="btn" type="submit" style="width:100%;margin-top:16px">保存批次</button>
  </form>
  <p class="note" style="margin-top:14px">录入的是有机肥投入品批次信息，非有机产品认证声明。</p>
@endsection
