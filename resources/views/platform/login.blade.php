<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@vite('resources/css/app.css')
<title>平台后台登录 · 光彩云村庄</title>
<style>
  .login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background:
      radial-gradient(1200px 600px at 80% -10%, var(--color-brand-50), transparent 60%),
      radial-gradient(900px 500px at -10% 110%, var(--color-accent-300), transparent 55%),
      var(--ds-bg-base);
  }
  .login-card {
    width: 100%;
    max-width: 400px;
    background: var(--ds-bg-layer-1);
    border: 1px solid var(--ds-border-1);
    border-radius: var(--ds-r-lg);
    padding: 36px 32px;
    box-shadow: var(--ds-shadow-elev);
  }
  .login-head { text-align: center; margin-bottom: 24px; }
  .login-head .brand-mark {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-serif);
    font-size: 22px;
    font-weight: 700;
    color: var(--color-brand-500);
    margin-bottom: 8px;
  }
  .login-head .brand-dot {
    width: 12px; height: 12px; border-radius: 999px;
    background: var(--color-brand-500);
    box-shadow: 0 0 0 5px var(--color-brand-50);
  }
  .login-head .sub { font-size: var(--ds-body-s); color: var(--ds-text-mute); }
</style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-head">
      <div class="brand-mark">
        <span class="brand-dot" aria-hidden="true"></span>
        光彩云村庄
      </div>
      <p class="sub">平台管理后台</p>
    </div>

    @if ($errors->any())
      <div class="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('platform.login.post') }}">
      @csrf
      <div class="field">
        <label>账号</label>
        <input class="input" name="account" value="{{ old('account') }}" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label>密码</label>
        <input class="input" type="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">登 录</button>
    </form>
  </div>
</div>
</body>
</html>