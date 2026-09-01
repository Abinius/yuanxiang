@extends('layouts.site')

@section('title', $adoption->named_label . ' · 铭牌')

@section('content')
  <div class="panel" style="max-width:420px;margin:0 auto;text-align:center">
    <h1 class="page-title" style="text-align:center;margin-bottom:16px">{{ $adoption->named_label }}</h1>
    @include('site.partials.nameplate', ['adoption' => $adoption, 'shareable' => true])

    <div class="mt-4">
      <button class="btn btn-primary btn-block btn-lg" id="copy-link" type="button">复制铭牌链接</button>
      <p class="note text-xs" style="margin-top:10px">
        可截图后分享到朋友圈或好友;微信内点右上角 ··· 可转发。
      </p>
    </div>

    <div class="mt-3 text-center">
      <a class="back-link" href="{{ route('tenant.my.plot', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">返回我的田</a>
    </div>
  </div>

  <script>
    (function () {
      var btn = document.getElementById('copy-link');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var url = location.href;
        var done = function () { btn.textContent = '已复制'; };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(done).catch(fallback);
        } else { fallback(); }
        function fallback() {
          var t = document.createElement('textarea');
          t.value = url; document.body.appendChild(t); t.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          document.body.removeChild(t);
        }
      });
    })();
  </script>
@endsection