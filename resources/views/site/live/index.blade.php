@extends('layouts.site')

@section('title', '实时监控')

@section('content')
  <section class="section">
    <div class="section-title"><span>实时监控</span></div>
    <p class="lede">画面涉及家人与现场人员肖像,已获授权展示。</p>
  </section>

  @forelse ($cameras as $camera)
    <a class="product-card mb-3" style="margin-bottom:12px"
       href="{{ route('tenant.live.show', ['tenant' => $tenant->slug, 'camera' => $camera]) }}">
      <div class="flex justify-between items-center">
        <div>
          <div class="code">{{ $camera->name }}</div>
          <div class="meta">{{ $camera->plot?->code ?? '未绑定田块' }} · {{ $camera->provider }}</div>
        </div>
        <span class="tag tag-dot {{ $camera->status === 'online' ? 'tag-available' : 'tag-off' }}">
          {{ $camera->status === 'online' ? '在线' : '离线' }}
        </span>
      </div>
    </a>
  @empty
    <div class="empty">
      <div class="empty-icon">📹</div>
      <div>摄像头正在陆续安装中(P3 进行中)</div>
      <div class="empty-hint">上线后这里可看直播 / 延时 / 回看。</div>
    </div>
  @endforelse
@endsection