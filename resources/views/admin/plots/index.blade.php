@extends('layouts.dashboard')

@section('title', '地块管理')

@section('nav_right')
  <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}">看板</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <div class="page-header">
    <h1 class="page-title">地块管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.plots.create', ['tenant' => $tenant->slug]) }}">添加地块</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if (session('error'))
    <div class="alert">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-5">田地由后台动态录入（不再硬编码）。为每块田写种植故事，认养详情页会在「地块故事」展示。在约认养的地块不可删除，可下架。</p>

  <div class="card-grid grid-2">
    @forelse ($plots as $plot)
      <div class="card" style="padding:16px">
        <div class="flex justify-between items-center mb-2">
          <span class="font-medium serif text-brand">{{ $plot->code }}</span>
          <span class="tag {{ $plot->status->value === 'available' ? 'tag-available' : 'tag-off' }}">{{ $plot->status->value }}</span>
        </div>
        <div class="flex gap-2 mb-3">
          <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.plots.edit', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">编辑</a>
          @if ($plot->hasInFlightAdoptions())
            <span class="tag tag-off" title="有在约认养，无法删除">在约·不可删</span>
          @else
            <form method="POST" action="{{ route('tenant.admin.plots.destroy', ['tenant' => $tenant->slug, 'plot' => $plot]) }}" onsubmit="return confirm('确认删除 {{ $plot->code }}？')">
              @csrf
              @method('DELETE')
              <button class="btn btn-ghost btn-sm" type="submit">删除</button>
            </form>
          @endif
        </div>
        <form method="POST" action="{{ route('tenant.admin.plots.story', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">
          @csrf
          <textarea class="textarea" name="story" rows="3" maxlength="1000" placeholder="例:这块田挨着涝坝,晨露重,夏果格外甜。">{{ $plot->story }}</textarea>
          <button class="btn btn-primary btn-sm mt-2" type="submit">保存故事</button>
        </form>
      </div>
    @empty
      <p class="note text-xs">还没有地块。点「添加地块」开始。</p>
    @endforelse
  </div>
@endsection
