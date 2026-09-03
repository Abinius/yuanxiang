@extends('layouts.dashboard')

@section('title', '平台账号管理')
@section('nav_right')
  <a href="{{ route('platform.dashboard') }}">看板</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('platform.logout') }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">平台账号管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('platform.users.create') }}">+ 新建账号</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-4">管理商户管理员（绑租户）与平台管理员。可跨租户建 tenant_admin。</p>

  <form method="GET" class="flex gap-2 mb-4" style="flex-wrap:wrap">
    <input class="input" name="q" value="{{ request('q') }}" placeholder="昵称/手机号/用户名">
    <select class="input" name="role">
      <option value="">全部角色</option>
      <option value="tenant_admin" @selected(request('role')==='tenant_admin')>商户管理员</option>
      <option value="platform_admin" @selected(request('role')==='platform_admin')>平台管理员</option>
    </select>
    <button class="btn btn-ghost btn-sm" type="submit">筛选</button>
  </form>

  <table class="table">
    <thead>
      <tr><th>昵称</th><th>手机号</th><th>角色</th><th>租户</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody>
      @forelse ($users as $u)
        <tr>
          <td>{{ $u->nickname }}</td>
          <td>{{ $u->phone }}</td>
          <td>{{ $u->role->label() }}</td>
          <td class="text-xs muted">{{ $u->tenant?->name ?? '—' }}</td>
          <td>
            @if ($u->is_disabled)
              <span class="tag tag-warn">已禁用</span>
            @else
              <span class="tag tag-ok">正常</span>
            @endif
          </td>
          <td>
            <a class="text-sm" href="{{ route('platform.users.edit', ['user' => $u]) }}">编辑</a>
            <form method="POST" action="{{ route('platform.users.toggle', ['user' => $u]) }}" class="inline">
              @csrf
              <button class="btn btn-ghost btn-sm" type="submit" @if($u->id === auth()->id()) disabled @endif>{{ $u->is_disabled ? '启用' : '禁用' }}</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-xs muted">暂无账号。</td></tr>
      @endforelse
    </tbody>
  </table>
@endsection
