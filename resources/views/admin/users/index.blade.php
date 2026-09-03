@extends('layouts.dashboard')

@section('title', '账号管理')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">账号管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.users.create', ['tenant' => $tenant->slug]) }}">+ 新建账号</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-4">管理本村庄的家人 / 云乡民 / 商户管理员账号。可禁用、改密；平台管理员须在平台后台管理。</p>

  <form method="GET" class="flex gap-2 mb-4">
    <input class="input" name="q" value="{{ request('q') }}" placeholder="昵称/手机号/用户名搜索">
    <button class="btn btn-ghost btn-sm" type="submit">搜索</button>
  </form>

  <table class="table">
    <thead>
      <tr><th>昵称</th><th>手机号</th><th>角色</th><th>状态</th><th>注册</th><th>操作</th></tr>
    </thead>
    <tbody>
      @forelse ($users as $u)
        <tr>
          <td>{{ $u->nickname }}</td>
          <td>{{ $u->phone }}</td>
          <td>{{ $u->role->label() }}</td>
          <td>
            @if ($u->is_disabled)
              <span class="tag tag-warn">已禁用</span>
            @else
              <span class="tag tag-ok">正常</span>
            @endif
          </td>
          <td class="text-xs muted">{{ $u->created_at?->format('Y-m-d') }}</td>
          <td>
            <a class="text-sm" href="{{ route('tenant.admin.users.edit', ['tenant' => $tenant->slug, 'user' => $u]) }}">编辑</a>
            <form method="POST" action="{{ route('tenant.admin.users.toggle', ['tenant' => $tenant->slug, 'user' => $u]) }}" class="inline">
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
