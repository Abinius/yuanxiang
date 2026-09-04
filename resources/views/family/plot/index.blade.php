@extends('layouts.dashboard')

@section('title', '地块录入')

@section('nav_right')
  <a href="{{ route('tenant.family.dashboard', ['tenant' => $tenant->slug]) }}">家人首页</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <div class="page-header">
    <h1 class="page-title">地块录入</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.family.plots.create', ['tenant' => $tenant->slug]) }}">添加地块</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-5">仅管理本基地地块。删除请联系租户管理员（在约认养地块不可删）。</p>

  <div class="card-grid grid-2">
    @forelse ($plots as $plot)
      <div class="card" style="padding:16px">
        <div class="flex justify-between items-center mb-2">
          <span class="font-medium serif text-brand">{{ $plot->code }}</span>
          <span class="tag {{ $plot->status->value === 'available' ? 'tag-available' : 'tag-off' }}">{{ $plot->status->label() }}</span>
        </div>
        <a class="btn btn-ghost btn-sm" href="{{ route('tenant.family.plots.edit', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">编辑</a>
      </div>
    @empty
      <p class="note text-xs">本基地还没有地块。</p>
    @endforelse
  </div>
@endsection
