@extends('layouts.dashboard')

@section('title', '溯源码打印')

@section('nav_right')
  <a href="{{ route('tenant.admin.trace-codes.index', ['tenant' => $tenant->slug]) }}">溯源码列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <div class="no-print flex justify-between items-center mb-4">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">溯源码打印</h1>
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">打印标签</button>
  </div>
  <p class="note text-xs no-print mb-4">
    打印时自动隐藏导航与本按钮;建议 <span class="mono">80×50mm</span> 不干胶标签纸。
  </p>

  <div class="label-grid">
    @foreach ($traceCodes as $tc)
      <div class="label card" style="text-align:center;border:1px dashed var(--ds-border-2);page-break-inside:avoid">
        <div class="qr" data-url="{{ url('/t/'.$tenant->slug.'/s/'.$tc->code) }}"></div>
        <div class="mono font-bold text-brand" style="font-size:14px;word-break:break-all">{{ $tc->code }}</div>
        <div class="muted text-xs" style="margin-top:6px">
          {{ $tc->plot?->code ?? '—' }} · 有机肥(NXLB)投入品 · 检测合格
        </div>
      </div>
    @endforeach
  </div>

  <style>
    .label-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; }
    .label-grid .qr { margin:0 auto 10px; min-height:96px; }
    @media print {
      .nav-admin, .no-print { display:none !important; }
      .main-admin { max-width:none; margin:0; padding:8px 16px; }
      .label-grid { grid-template-columns:repeat(3,1fr); gap:8px; }
    }
  </style>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    (function () {
      document.querySelectorAll('.qr').forEach(function (el) {
        var url = el.getAttribute('data-url');
        if (typeof QRCode === 'function') {
          new QRCode(el, { text: url, width: 96, height: 96 });
        } else {
          el.textContent = url;
        }
      });
    })();
  </script>
@endsection