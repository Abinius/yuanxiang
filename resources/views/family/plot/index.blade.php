@extends('layouts.dashboard')

@section('title', '地块录入')

@section('nav_right')
  <a href="{{ route('tenant.family.dashboard', []) }}">家人首页</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <div class="page-header">
    <h1 class="page-title">地块录入</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.family.plots.create', []) }}">添加地块</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-5">仅管理本基地地块。删除会一并取消该地块的未付款订单；已付款或在约认养的地块不可删。</p>

  <div class="card-grid grid-2">
    @forelse ($plots as $plot)
      @php($blocker = $plot->deletionBlocker())
      <div class="card" style="padding:16px">
        <div class="flex justify-between items-center mb-2">
          <span class="font-medium serif text-brand">{{ $plot->code }}</span>
          <span class="tag {{ $plot->status->value === 'available' ? 'tag-available' : 'tag-off' }}">{{ $plot->status->label() }}</span>
        </div>
        <div class="flex gap-2 mb-2">
          <a class="btn btn-ghost btn-sm" href="{{ route('tenant.family.plots.edit', ['plot' => $plot]) }}">编辑</a>
          @if (! $blocker)
            <form method="POST" action="{{ route('tenant.family.plots.destroy', ['plot' => $plot]) }}" onsubmit="return confirm('确认删除 {{ $plot->code }}（{{ $plot->mu_area }} 亩）？删除后不可恢复。')">
              @csrf
              @method('DELETE')
              <button class="btn btn-ghost btn-sm" type="submit">删除</button>
            </form>
          @endif
        </div>
        @if ($blocker)
          <p class="note text-xs mb-3">{{ $blocker }}</p>
        @endif
      </div>
    @empty
      <p class="note text-xs">本基地还没有地块。</p>
    @endforelse
  </div>
@endsection
