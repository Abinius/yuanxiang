@extends('layouts.dashboard')

@section('title', $user->exists ? '编辑平台账号' : '新建平台账号')
@section('nav_right')
  <a href="{{ route('platform.dashboard') }}">看板</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('platform.logout') }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">{{ $user->exists ? '编辑平台账号' : '新建平台账号' }}</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $user->exists ? route('platform.users.update', ['user' => $user]) : route('platform.users.store') }}" style="max-width:520px">
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
      <select class="input" name="role" id="role-select">
        <option value="tenant_admin" @selected(old('role', $user->role?->value)==='tenant_admin')>商户管理员</option>
        <option value="platform_admin" @selected(old('role', $user->role?->value)==='platform_admin')>平台管理员</option>
      </select>
    </div>

    <div class="field" id="tenant-field">
      <label>所属村庄 *</label>
      <select class="input" name="tenant_id">
        @foreach ($tenants as $t)
          <option value="{{ $t->id }}" @selected((int)old('tenant_id', $user->tenant_id)===$t->id)>{{ $t->name }}（{{ $t->slug }}）</option>
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
      <a class="btn btn-ghost" href="{{ route('platform.users.index') }}">返回</a>
    </div>
  </form>

  <script>
    // 选平台管理员时隐藏租户字段
    (function(){
      var sel = document.getElementById('role-select');
      var tf = document.getElementById('tenant-field');
      function sync(){ tf.style.display = sel.value === 'platform_admin' ? 'none' : ''; }
      sel.addEventListener('change', sync); sync();
    })();
  </script>
@endsection
