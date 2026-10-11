@extends('layouts.dashboard')
@php($batch ??= null)

@section('title', $batch?->exists ? '编辑有机肥批次' : '录入有机肥批次')

@section('nav_right')
  @include('family.partials.nav')
@endsection

@section('content')
  <h2 class="admin-h1 mb-4">{{ $batch?->exists ? '编辑有机肥批次' : '录入有机肥批次' }}</h2>
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif
  <form class="form-single" method="POST"
        action="{{ $batch?->exists ? route('tenant.family.fertilizer.update', ['batch' => $batch]) : route('tenant.family.fertilizer.store', []) }}">
    @csrf
    <div class="field">
      <label>批次号</label>
      <input class="input" name="batch_no" required maxlength="60" value="{{ old('batch_no', $batch?->batch_no) }}">
    </div>

    <div class="field">
      <label>生产日期</label>
      <input class="input" type="date" name="produced_at" required value="{{ old('produced_at', $batch?->produced_at?->toDateString()) }}">
    </div>

    <div class="field">
      <label>NXLB 参考编号</label>
      <input class="input" name="nxlb_ref" maxlength="120" value="{{ old('nxlb_ref', $batch?->nxlb_ref) }}">
    </div>

    <div class="field">
      <label>配料 / 成分</label>
      <textarea class="textarea" name="ingredients" rows="3" maxlength="1000">{{ old('ingredients', $batch?->ingredients) }}</textarea>
    </div>

    <div class="field">
      <label>检测报告链接（可选）</label>
      <input class="input" name="test_report_url" maxlength="500" value="{{ old('test_report_url', $batch?->test_report_url) }}">
    </div>

    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:16px">{{ $batch?->exists ? '保存修改' : '保存批次' }}</button>
  </form>
  <p class="note" style="margin-top:14px">录入的是有机肥投入品批次信息，非有机产品认证声明。</p>
@endsection
