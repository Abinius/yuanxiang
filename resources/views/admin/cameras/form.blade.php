@extends('layouts.dashboard')

@section('title', $camera->exists ? '编辑摄像头' : '添加摄像头')

@section('nav_right')
  <a href="{{ route('tenant.admin.cameras.index', ['tenant' => $tenant->slug]) }}">摄像头列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">{{ $camera->exists ? '编辑摄像头' : '添加摄像头' }}</h1>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $camera->exists ? route('tenant.admin.cameras.update', ['tenant' => $tenant->slug, 'camera' => $camera]) : route('tenant.admin.cameras.store', ['tenant' => $tenant->slug]) }}">
    @csrf
    @if ($camera->exists)
      @method('PUT')
    @endif

    <div class="card-grid grid-2 mb-4">
      <div class="field">
        <label>名称</label>
        <input class="input" name="name" required maxlength="60" value="{{ old('name', $camera->name ?? '') }}">
      </div>
      <div class="field">
        <label>设备号</label>
        <input class="input" name="device_no" required maxlength="80" value="{{ old('device_no', $camera->device_no ?? '') }}">
      </div>
    </div>

    <div class="field">
      <label>厂家 / 平台</label>
      <input class="input" name="provider" required maxlength="40" value="{{ old('provider', $camera->provider ?? 'ezviz') }}" placeholder="ezviz / aliyun">
    </div>

    <div class="field">
      <label>绑定田块(可选)</label>
      <select name="plot_id" class="select">
        <option value="">— 不绑定 —</option>
        @foreach ($plots as $plot)
          <option value="{{ $plot->id }}" @selected(old('plot_id', $camera->plot_id) == $plot->id)>{{ $plot->code }}</option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label>直播流地址(HLS)</label>
      <input class="input" name="stream_url" maxlength="500" value="{{ old('stream_url', $camera->stream_url ?? '') }}" placeholder="https://...m3u8">
      <div class="field-hint">HLS 直播流,如萤石云 stream_url。</div>
    </div>

    <div class="field">
      <label>回看地址(可选)</label>
      <input class="input" name="playback_url" maxlength="500" value="{{ old('playback_url', $camera->playback_url ?? '') }}">
    </div>

    <div class="field">
      <label>访问凭证 / Token(可选,仅存 DB)</label>
      <input class="input" name="token" maxlength="500" value="{{ old('token', $camera->token ?? '') }}">
      <div class="field-hint">密钥走环境变量,不入仓。</div>
    </div>

    <div class="field">
      <label>状态</label>
      <select name="status" class="select">
        <option value="offline" @selected(old('status', $camera->status ?? 'offline') === 'offline')>离线</option>
        <option value="online" @selected(old('status', $camera->status ?? 'offline') === 'online')>在线</option>
      </select>
    </div>

    <button class="btn btn-primary btn-block btn-lg" type="submit">{{ $camera->exists ? '保存修改' : '添加摄像头' }}</button>
  </form>
@endsection