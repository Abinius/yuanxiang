@extends('layouts.dashboard')

@section('title', '微信与支付')

@section('nav_right')
  <a href="{{ route('tenant.admin.dashboard', []) }}">后台</a>
  <a href="{{ route('tenant.admin.settings.edit', []) }}">站点设置</a>
  <a href="{{ route('tenant.home', []) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">微信与支付</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form id="integrations-form" method="POST" action="{{ route('tenant.admin.integrations.update', []) }}">
    @csrf
    @method('PUT')

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>微信公众号（登录 / 模板推送）</span></div>
      <div class="field" style="max-width:660px">
        <label class="inline-label" style="cursor:pointer">
          <input type="checkbox" name="wechat[mock]" value="1"
                 {{ $integration['mock'] ? 'checked' : '' }}{{ \App\Support\IntegrationConfig::mockEditable() ? '' : ' disabled' }}>
          本地模拟模式（勾选后微信登录与模板推送不真发，仅写 mock 用户与 push_messages）
        </label>
        <div class="field-hint">
          {{ \App\Support\IntegrationConfig::mockEditable()
              ? '上线前必须取消勾选，并把 app_id、secret、模板 ID 配齐。'
              : '生产环境只读：由 env WECHAT_MOCK 决定，后台改不动——漏配时默认 false（不 mock），避免勾错一次就静默关掉真实支付。' }}
        </div>
      </div>
      <div class="card-grid grid-2" style="margin-top:14px">
        <div class="field">
          <label>公众号 appid</label>
          <input class="input" type="text" name="wechat[app_id]" maxlength="64"
                 value="{{ old('wechat.app_id', $integration['app_id']) }}" placeholder="wx…（留空沿用 env WECHAT_APP_ID）">
        </div>
        <div class="field">
          <label>授权范围</label>
          <select class="input" name="wechat[scope]">
            <option value="snsapi_userinfo" {{ old('wechat.scope', $integration['scope']) === 'snsapi_userinfo' ? 'selected' : '' }}>snsapi_userinfo（获取头像昵称）</option>
            <option value="snsapi_base" {{ old('wechat.scope', $integration['scope']) === 'snsapi_base' ? 'selected' : '' }}>snsapi_base（静默获取 openid）</option>
          </select>
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>模板消息（服务号认证后填模板 ID 即真发）</span></div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>开播提醒 live_notice</label>
          <input class="input" type="text" name="wechat[templates][live_notice]" maxlength="64"
                 value="{{ old('wechat.templates.live_notice', $integration['templates']['live_notice']) }}" placeholder="留空则 mock 落库不真发">
        </div>
        <div class="field">
          <label>内容动态 content</label>
          <input class="input" type="text" name="wechat[templates][content]" maxlength="64"
                 value="{{ old('wechat.templates.content', $integration['templates']['content']) }}" placeholder="留空则 mock 落库不真发">
        </div>
        <div class="field">
          <label>采收通知 harvest_notice</label>
          <input class="input" type="text" name="wechat[templates][harvest_notice]" maxlength="64"
                 value="{{ old('wechat.templates.harvest_notice', $integration['templates']['harvest_notice']) }}" placeholder="留空则 mock 落库不真发">
        </div>
        <div class="field">
          <label>续费提醒 renewal_notice</label>
          <input class="input" type="text" name="wechat[templates][renewal_notice]" maxlength="64"
                 value="{{ old('wechat.templates.renewal_notice', $integration['templates']['renewal_notice']) }}" placeholder="留空则 mock 落库不真发">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>微信支付</span></div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>商户号 mch_id</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input id="mch-id" class="input" type="password" name="pay[mch_id]" maxlength="32" autocomplete="off"
                   value="{{ old('pay.mch_id', $integration['mch_id']) }}" placeholder="留空沿用 env WECHAT_MCH_ID">
            <label class="inline-label" style="white-space:nowrap;cursor:pointer">
              <input type="checkbox" id="mch-show"> 显示
            </label>
          </div>
        </div>
        <div class="field">
          <label>接入模式</label>
          <select class="input" name="pay[mode]">
            <option value="normal" {{ old('pay.mode', $integration['mode']) === 'normal' ? 'selected' : '' }}>normal（普通商户）</option>
            <option value="service" {{ old('pay.mode', $integration['mode']) === 'service' ? 'selected' : '' }}>service（服务商）</option>
          </select>
        </div>
      </div>
      <div class="field" style="max-width:640px">
        <label>支付回调地址</label>
        <input class="input" type="url" name="pay[notify_url]" maxlength="500"
               value="{{ old('pay.notify_url', $integration['notify_url']) }}" placeholder="https://…/pay/wechat/notify">
        <div class="field-hint">须为 https，且域名与 APP_URL 一致（当前主机：{{ (parse_url(config('app.url'), PHP_URL_HOST) ?: '未配置') }}）。</div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>密钥与证书（只读 · 不进后台）</span></div>
      <p class="note text-xs" style="margin:0 0 10px">
        方案 A：密钥类配置一律留在服务器环境变量与文件系统，不落数据库、不经后台表单。
        改动后需重启 PHP 进程生效（后台其余配置保存后下一次请求即生效，无需重启）。
      </p>
      <table class="table" style="width:100%;max-width:760px">
        <tr>
          <td style="font-weight:600">公众号 secret</td>
          <td>{{ filled(config('wechat.secret')) ? '已配置' : '未配置' }}</td>
          <td class="note text-xs">env <code>WECHAT_SECRET</code></td>
        </tr>
        <tr>
          <td style="font-weight:600">APIv3 密钥</td>
          <td>{{ filled(config('pay.wechat.default.mch_secret_key')) ? '已配置' : '未配置' }}</td>
          <td class="note text-xs">env <code>WECHAT_APIV3_KEY</code></td>
        </tr>
        <tr>
          <td style="font-weight:600">商户私钥证书</td>
          <td>{{ filled(config('pay.wechat.default.mch_secret_cert')) ? '已配置' : '未配置' }}</td>
          <td class="note text-xs">env <code>WECHAT_SECRET_CERT_PATH</code></td>
        </tr>
        <tr>
          <td style="font-weight:600">商户公钥证书</td>
          <td>{{ filled(config('pay.wechat.default.mch_public_cert_path')) ? '已配置' : '未配置' }}</td>
          <td class="note text-xs">env <code>WECHAT_PUBLIC_CERT_PATH</code></td>
        </tr>
      </table>
    </div>

    <button class="btn btn-primary btn-block btn-lg" type="submit">保存配置</button>
  </form>

  <script>
    (function () {
      var show = document.getElementById('mch-show');
      var input = document.getElementById('mch-id');
      if (!show || !input) return;
      show.addEventListener('change', function () {
        input.type = show.checked ? 'text' : 'password';
      });
    })();
  </script>
@endsection
