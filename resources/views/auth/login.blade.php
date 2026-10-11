<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@vite('resources/css/app.css')
<title>登录 · {{ $tenant->name }}</title>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-head">
      <div class="brand-mark">
        <span class="brand-dot" aria-hidden="true"></span>
        陌上原乡
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
      <form method="POST" action="{{ route('tenant.login.post', []) }}" id="login-form">
        @csrf
        <div class="field">
          <label>手机号 / 用户名 / 邮箱</label>
          <input class="input" name="account" value="{{ old('account') }}" required autofocus autocomplete="username">
        </div>
        <div class="field">
          <label>密码</label>
          <input class="input" type="password" name="password" required autocomplete="current-password">
        </div>
        <label class="agreement-check">
          {{-- 不加 required：浏览器原生校验会先拦下提交，导致下面的弹窗逻辑拿不到 submit 事件。
               同意校验由服务端 'agreed' => ['accepted'] 兜底（无 JS 时提交缺 agreed 仍返回 422）。 --}}
          <input type="checkbox" name="agreed" value="1" id="agreed-check">
          <span>
            我已阅读并同意
            <a href="{{ route('tenant.agreement.show', ['key' => 'service']) }}">《服务协议》</a>
            与
            <a href="{{ route('tenant.agreement.show', ['key' => 'privacy']) }}">《隐私政策》</a>
          </span>
        </label>
        <button class="btn btn-primary btn-block btn-lg" type="submit" id="login-submit">登 录</button>
      </form>
      <p class="login-hint">忘记密码？请先微信登录或联系客服</p>
    </div>

    <div class="login-panel" id="wechat">
      <a class="btn btn-wechat btn-block btn-lg" href="{{ route('tenant.login.wechat', []) }}">
        微信一键登录
      </a>
      <p class="login-hint">微信授权即表示同意《服务协议》《隐私政策》，授权后自动注册为云乡民</p>
    </div>
  </div>
</div>

{{-- R1 同意确认弹窗：勾选框未勾选时提交触发。用页面内模态而非跳页——跳转会丢掉刚输入的账号密码。 --}}
<div class="consent-modal" id="consent-modal" hidden role="dialog" aria-modal="true" aria-labelledby="consent-title">
  <div class="dialog">
    <h2 id="consent-title">使用陌上原乡即表示你同意以下要点</h2>
    <ul class="consent-terms">
      <li>为完成认养、支付与配送，我们会收集你的手机号、收货地址与认养信息。</li>
      <li>支付信息由微信支付商户（宁夏花乌巷食品有限公司）处理，我们不留存卡号。</li>
      <li>摄像头画面仅对认养人开放，不对外公开。</li>
      <li>你可随时联系客服撤回同意；撤回后相关处理停止，但已完成的认养与交易记录依法保留。</li>
    </ul>
    <div class="consent-links">
      <a href="{{ route('tenant.agreement.show', ['key' => 'service']) }}">《服务协议》全文</a>
      <a href="{{ route('tenant.agreement.show', ['key' => 'privacy']) }}">《隐私政策》全文</a>
    </div>
    <div class="consent-actions">
      <button class="btn btn-ghost btn-lg" type="button" id="consent-dismiss">稍后再说</button>
      <button class="btn btn-primary btn-lg" type="button" id="consent-accept">同意并登录</button>
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

// R1：未勾选协议时拦截提交并弹出确认窗；点「同意并登录」自动勾选后走原生表单提交。
(function () {
  var form  = document.getElementById('login-form');
  var agree = document.getElementById('agreed-check');
  var modal = document.getElementById('consent-modal');
  if (!form || !agree || !modal) return;

  var accept  = document.getElementById('consent-accept');
  var dismiss = document.getElementById('consent-dismiss');
  var submitBtn = document.getElementById('login-submit');
  var busy = false;

  function open() {
    modal.hidden = false;
    document.body.classList.add('consent-open');
    accept.focus();
  }
  function close() {
    modal.hidden = true;
    document.body.classList.remove('consent-open');
  }

  form.addEventListener('submit', function (e) {
    if (agree.checked) {
      // 登录成功会 session regenerate 作废旧 token，连点第二次 POST 直接 419；
      // 真提交才锁按钮（未勾协议走弹窗分支时不能锁，否则 requestSubmit 无 submitter）。
      if (submitBtn) submitBtn.disabled = true;
      return;
    }
    if (busy) return;
    e.preventDefault();
    open();
  });

  // 必须走原生表单提交：保留 CSRF 校验、422 回跳与 session regenerate 既有链条，不改写为 fetch。
  accept.addEventListener('click', function () {
    if (busy) return;
    busy = true;
    accept.disabled = true;
    agree.checked = true;
    close();
    form.requestSubmit();
  });

  dismiss.addEventListener('click', close);
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) close();
  });
})();
</script>
</body>
</html>
