@extends('layouts.dashboard')

@section('title', '打单打印')

@section('content')
  <div class="no-print" style="margin-bottom:16px">
    <button class="btn" type="button" onclick="window.print()">打印打单</button>
    <p class="note" style="font-size:12px;margin-top:8px">打印时自动隐藏导航与本按钮。</p>
  </div>

  <div class="picking-list">
    @foreach ($deliveries as $d)
      <div class="sheet">
        <div class="head">光彩云村庄 · 打单</div>
        <div>认养单：{{ $d->adoption?->adoption_no ?? '—' }}</div>
        <div>认养人：{{ $d->adoption?->user?->nickname ?? '—' }}（{{ $d->adoption?->user?->phone ?? '—' }}）</div>
        <div>田块：{{ $d->harvest?->plot?->code ?? '—' }} · {{ $d->harvest?->season_year ?? '—' }} 年度</div>
        <div>规格：{{ $d->spec['packing'] ?? '保底分装' }}</div>
        <div class="addr">
          @if ($d->address)
            收：{{ $d->address->name }} {{ $d->address->phone }}<br>
            {{ $d->address->province ?? '' }}{{ $d->address->city ?? '' }}{{ $d->address->district ?? '' }}{{ $d->address->detail }}
          @else
            收件地址未设置
          @endif
        </div>
      </div>
    @endforeach
  </div>

  <style>
    .picking-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
    .sheet{border:1px dashed #c9bfae;border-radius:8px;padding:14px 16px;background:#fff;font-size:13px;line-height:1.9;page-break-inside:avoid}
    .sheet .head{font-weight:700;color:var(--primary);margin-bottom:6px}
    .sheet .addr{color:var(--muted);margin-top:6px}
    @media print {
      nav,.no-print{display:none !important}
      main{max-width:none;margin:0;padding:8px}
      .picking-list{grid-template-columns:repeat(2,1fr);gap:8px}
    }
  </style>
@endsection
