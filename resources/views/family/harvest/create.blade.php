@extends('layouts.dashboard')
@php($harvest ??= null)

@section('title', $harvest?->exists ? '编辑采收' : '录入采收')

@section('nav_right')
  @include('family.partials.nav')
@endsection

@section('content')
  <h2 class="admin-h1 mb-4">{{ $harvest?->exists ? '编辑采收' : '录入采收' }}</h2>
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif
  <form class="form-single" method="POST"
        action="{{ $harvest?->exists ? route('tenant.family.harvest.update', ['harvest' => $harvest]) : route('tenant.family.harvest.store', []) }}">
    @csrf
    <div class="field">
      <label>田块</label>
      <select class="select" name="plot_id" required>
        @foreach ($plots as $plot)
          <option value="{{ $plot->id }}" @selected(old('plot_id', $harvest?->plot_id) == $plot->id)>{{ $plot->code }}</option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label>采收年度</label>
      <input class="input" type="number" name="season_year" min="2000" max="2100" value="{{ old('season_year', $harvest?->season_year ?? now()->year) }}">
    </div>

    <div class="field">
      <label>采收日期</label>
      <input class="input" type="date" name="harvested_at" required value="{{ old('harvested_at', $harvest?->harvested_at?->toDateString()) }}">
    </div>

    <div class="field">
      <label>干重（kg）</label>
      <input class="input" type="number" step="0.01" name="dry_weight_kg" required value="{{ old('dry_weight_kg', $harvest?->dry_weight_kg) }}">
    </div>

    <div class="field">
      <label>品质等级（可选）</label>
      <input class="input" name="quality_grade" maxlength="20" value="{{ old('quality_grade', $harvest?->quality_grade) }}">
    </div>

    <div class="field">
      <label>备注</label>
      <textarea class="textarea" name="notes" rows="3" maxlength="500">{{ old('notes', $harvest?->notes) }}</textarea>
    </div>

    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:16px">{{ $harvest?->exists ? '保存修改' : '保存采收' }}</button>
  </form>
@endsection
