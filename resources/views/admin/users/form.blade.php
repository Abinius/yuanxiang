@extends('layouts.dashboard')

@section('title', $user->exists ? '编辑账号' : '新建账号')

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">{{ $user->exists ? '编辑账号' : '新建账号' }}</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $user->exists ? route('tenant.admin.users.update', ['tenant' => $tenant->slug, 'user' => $user]) : route('tenant.admin.users.store', ['tenant' => $tenant->slug]) }}" style="max-width:520px">
    @csrf
    @method($user->exists ? 'PUT' : 'POST')

    <div class="field">
      <label>昵称 *</label>
      <input class="input" name="nickname" value="{{ old('nickname', $user->nickname) }}" required>
    </div>

    <div class="field">
      <label>手机号 *</label>
      <input class="input" name="phone" value="{{ old('phone', $user->phone) }}" required placeholder="1开头 11 位">
    </div>

    <div class="field">
      <label>用户名（登录用，可空）</label>
      <input class="input" name="username" value="{{ old('username', $user->username) }}">
    </div>

    <div class="field">
      <label>角色 *</label>
      <select class="input" name="role">
        @foreach ($roles as $value => $label)
          <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label>密码 {{ $user->exists ? '（留空不改）' : '*' }}</label>
      <input class="input" type="password" name="password" {{ $user->exists ? '' : 'required' }} minlength="6">
    </div>

    <div class="field">
      <label>生日（可空，用于生日权益）</label>
      <input class="input" type="date" name="birthday" value="{{ old('birthday', $user->birthday?->format('Y-m-d')) }}">
    </div>

    <div class="flex gap-2 mt-4">
      <button class="btn btn-primary" type="submit">保存</button>
      <a class="btn btn-ghost" href="{{ route('tenant.admin.users.index', ['tenant' => $tenant->slug]) }}">返回</a>
    </div>
  </form>

  @if ($user->exists)
    <hr class="my-6" style="border:0;border-top:1px solid var(--ds-border-l1)">
    <h3 class="mb-2" style="font-size:var(--ds-h3)">重置密码</h3>
    <form method="POST" action="{{ route('tenant.admin.users.reset-password', ['tenant' => $tenant->slug, 'user' => $user]) }}" style="max-width:520px">
      @csrf
      <div class="field">
        <label>新密码 *</label>
        <input class="input" type="password" name="password" required minlength="6">
      </div>
      <button class="btn btn-ghost btn-sm" type="submit">重置密码</button>
    </form>
  @endif
@endsection
