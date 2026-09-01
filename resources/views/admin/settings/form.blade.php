@extends('layouts.dashboard')

@section('title', '站点设置')

@section('nav_right')
  <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}">后台</a>
  <a href="{{ route('tenant.admin.short-links.index', ['tenant' => $tenant->slug]) }}">短链接</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">站点设置</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('tenant.admin.settings.update', ['tenant' => $tenant->slug]) }}">
    @csrf
    @method('PUT')

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>品牌</span></div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>品牌主色(如 #B33A26)</label>
          <input class="input" name="brand_primary" maxlength="20" value="{{ old('brand_primary', $tenant->settings['brand']['primary'] ?? config('site.defaults.brand.primary')) }}">
        </div>
        <div class="field">
          <label>品牌辅色(如 #C9A227)</label>
          <input class="input" name="brand_accent" maxlength="20" value="{{ old('brand_accent', $tenant->settings['brand']['accent'] ?? config('site.defaults.brand.accent')) }}">
        </div>
      </div>
    </div>

    <div class="section" style="margin-bottom:24px">
      <div class="section-title"><span>SEO / 分享</span></div>
      <div class="field">
        <label>站点标题(SEO title)</label>
        <input class="input" name="seo_title" maxlength="60" value="{{ old('seo_title', $tenant->settings['seo']['title'] ?? config('site.defaults.title')) }}">
      </div>
      <div class="field">
        <label>站点描述(SEO / 分享 description)</label>
        <textarea class="textarea" name="seo_description" rows="3" maxlength="200">{{ old('seo_description', $tenant->settings['seo']['description'] ?? config('site.defaults.description')) }}</textarea>
      </div>
      <div class="field">
        <label>分享图片 URL(og:image,需绝对地址)</label>
        <input class="input" name="seo_image" maxlength="500" value="{{ old('seo_image', $tenant->settings['seo']['image'] ?? '') }}" placeholder="https://.../logo.png">
      </div>
    </div>

    <div class="section">
      <div class="section-title"><span>页脚 / 联系方式</span></div>
      <div class="field">
        <label>页脚版权</label>
        <input class="input" name="footer_copyright" maxlength="120" value="{{ old('footer_copyright', $tenant->settings['footer_copyright'] ?? config('site.defaults.footer_copyright')) }}">
      </div>
      <div class="card-grid grid-2">
        <div class="field">
          <label>ICP 备案号</label>
          <input class="input" name="icp_number" maxlength="60" value="{{ old('icp_number', $tenant->settings['icp_number'] ?? '') }}">
        </div>
        <div class="field">
          <label>联系方式(客服 / 微信)</label>
          <input class="input" name="contact" maxlength="200" value="{{ old('contact', $tenant->settings['contact'] ?? '') }}">
        </div>
      </div>
    </div>

    <button class="btn btn-primary btn-block btn-lg" type="submit">保存设置</button>
  </form>
  <p class="note text-xs" style="margin-top:14px">
    品牌色即时反映到前台 / 后台主题;SEO 文案在分享卡片与搜索引擎预览中生效。
  </p>
@endsection