@extends('layouts.dashboard')

@section('title', '生成短链')

@section('nav_right')
  <a href="{{ route('tenant.admin.short-links.index', ['tenant' => $tenant->slug]) }}">短链列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">生成短链</h1>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('tenant.admin.short-links.store', ['tenant' => $tenant->slug]) }}">
      @csrf
      <div class="field">
        <label>目标地址(完整 URL,可粘贴溯源/扫码/认养/落地页链接)</label>
        <input class="input" name="target_url" required maxlength="500" value="{{ old('target_url') }}">
      </div>

      <div class="field">
        <label>自定义短码(可选,2-20 位小写字母/数字/连字符;留空随机)</label>
        <input class="input" name="code" maxlength="20" pattern="[a-z0-9-]+" value="{{ old('code') }}">
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">生成</button>
    </form>
    <p class="note text-xs" style="margin-top:14px">
      生成后跳转地址为 <span class="mono text-brand">/u/{code}</span>(公开),可用于分享卡片/物料二维码,并统计点击。
    </p>
  </div>
@endsection