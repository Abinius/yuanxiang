@extends('layouts.dashboard')

@section('title', '地块故事')

@section('content')
  <div class="page-header">
    <h1 class="page-title">地块故事</h1>
    <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}">返回看板</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-5">为每块田写一段种植故事/介绍，认养详情页会在「地块故事」卡片展示。</p>

  <div class="card-grid grid-2">
    @forelse ($plots as $plot)
      <div class="card" style="padding:16px">
        <div class="flex justify-between items-center mb-2">
          <span class="font-medium serif text-brand">{{ $plot->code }}</span>
          <span class="tag {{ $plot->status->value === 'available' ? 'tag-available' : 'tag-off' }}">{{ $plot->status->value }}</span>
        </div>
        <form method="POST" action="{{ route('tenant.admin.plots.story', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">
          @csrf
          <textarea class="textarea" name="story" rows="3" maxlength="1000" placeholder="例:这块田挨着涝坝,晨露重,夏果格外甜。">{{ $plot->story }}</textarea>
          <button class="btn btn-primary btn-sm mt-2" type="submit">保存故事</button>
        </form>
      </div>
    @empty
      <p class="note text-xs">还没有地块。</p>
    @endforelse
  </div>
@endsection