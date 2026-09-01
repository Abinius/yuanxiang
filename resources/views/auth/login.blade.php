<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="{{ asset('app.css') }}">
<title>登录 · {{ $tenant->name }}</title>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-head">
      <div class="brand-mark">
        <span class="brand-dot" aria-hidden="true"></span>
        光彩云村庄
      </div>
      <p class="sub">{{ $tenant->name }} · 云乡民登录</p>
    </div>

    @if ($errors->any())
      <div class="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
      <div class="ok">{{ session('status') }}</div>
    @endif

    <div class="login-tabs" role="tablist">
      <div class="tab active" data-tab="pwd" role="tab">账号登录</div>
      <div class="tab" data-tab="wechat" role="tab">微信登录</div>
    </div>

    <div class="login-panel active" id="pwd">
      <form method="POST" action="{{ route('tenant.login.post', ['tenant' => $tenant->slug]) }}">
        @csrf
        <div class="field">
          <label>手机号 / 用户名 / 邮箱</label>
          <input class="input" name="account" value="{{ old('account') }}" required autofocus autocomplete="username">
        </div>
        <div class="field">
          <label>密码</label>
          <input class="input" type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">登 录</button>
      </form>
      <p class="login-hint">忘记密码？请先微信登录或联系客服</p>
    </div>

    <div class="login-panel" id="wechat">
      <a class="btn btn-wechat btn-block btn-lg" href="{{ route('tenant.login.wechat', ['tenant' => $tenant->slug]) }}">
        微信一键登录
      </a>
      <p class="login-hint">微信授权后自动注册为云乡民</p>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
  document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
  document.querySelectorAll('.login-panel').forEach(x => x.classList.remove('active'));
  t.classList.add('active');
  document.getElementById(t.dataset.tab).classList.add('active');
}));
</script>
</body>
</html>