@extends('layouts.site')

@section('title', $camera->name)

@section('content')
  <div class="panel" style="max-width:720px;margin:0 auto">
    <div class="flex justify-between items-center mb-2">
      <h1 style="font-size:var(--ds-h2);margin:0">{{ $camera->name }}</h1>
      <span class="tag tag-dot {{ $camera->status === 'online' ? 'tag-available' : 'tag-off' }}">
        {{ $camera->status === 'online' ? '在线' : '离线' }}
      </span>
    </div>
    <div class="text-xs" style="color:var(--ds-text-mute);margin-bottom:16px">
      {{ $camera->plot?->code ?? '未绑定田块' }} · {{ $camera->provider }} · 设备号 {{ $camera->device_no }}
    </div>

    @if ($streamable)
      <div id="player-wrap" class="live-player">
        <video id="live-video" controls autoplay playsinline></video>
      </div>
      <div id="degrade" class="live-degrade" style="display:none">
        <p>摄像头暂时离线,可能因 4G 信号 / 太阳能供电中断,恢复后将补传延时短片。</p>
        @if ($camera->playback_url)
          <a class="btn btn-ghost mt-3" href="{{ $camera->playback_url }}" target="_blank" rel="noopener">查看回看片段</a>
        @endif
      </div>
      <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"
              crossorigin="anonymous"></script>
      <script>
        (function () {
          var video = document.getElementById('live-video');
          var src = @json($camera->stream_url);
          function degrade() {
            var w = document.getElementById('player-wrap');
            if (w) w.style.display = 'none';
            var d = document.getElementById('degrade');
            if (d) d.style.display = 'block';
          }
          if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = src;
            video.addEventListener('error', degrade);
          } else if (window.Hls) {
            var hls = new Hls();
            hls.loadSource(src);
            hls.attachMedia(video);
            hls.on(Hls.Events.ERROR, function (e, d) { if (d.fatal) degrade(); });
          } else { degrade(); }
        })();
      </script>
    @else
      <div class="live-degrade">
        <p>摄像头离线,可能因 4G 信号 / 太阳能供电中断,恢复后将补传延时短片。</p>
        @if ($camera->playback_url)
          <a class="btn btn-ghost mt-3" href="{{ $camera->playback_url }}" target="_blank" rel="noopener">查看回看片段</a>
        @endif
      </div>
    @endif

    <p class="note text-xs" style="margin-top:20px">画面涉及家人与现场人员肖像,已获授权展示。</p>
    <div class="mt-3">
      <a style="font-size:var(--ds-body-s);color:var(--ds-text-mute)" href="{{ route('tenant.live.index', ['tenant' => $tenant->slug]) }}">返回列表</a>
    </div>
  </div>
@endsection