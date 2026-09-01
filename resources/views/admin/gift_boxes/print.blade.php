@extends('layouts.dashboard')

@section('title', '礼盒贺卡')

@section('nav_right')
  <a href="{{ route('tenant.admin.gift-boxes.index', ['tenant' => $tenant->slug]) }}">礼盒列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <div class="no-print flex justify-between items-center mb-4">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">礼盒贺卡</h1>
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">打印贺卡</button>
  </div>
  <p class="note text-xs no-print mb-4">打印时自动隐藏导航与本按钮。</p>

  <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
    @foreach ($giftBoxes as $g)
      <div class="card" style="border:1px dashed var(--ds-border-2);font-size:13px;line-height:1.9;page-break-inside:avoid">
        <div class="serif font-bold text-brand mb-1" style="font-size:16px">{{ $g->festival->label() }}祝福 {{ $g->year }}</div>
        @if ($g->recipient_name)
          <div>致 {{ $g->recipient_name }}{{ $g->recipient_phone ? '('.$g->recipient_phone.')' : '' }}</div>
        @endif
        @if ($g->message)
          <div style="font-style:italic;margin:8px 0">{{ $g->message }}</div>
        @endif
        @if ($g->signature_image)
          <img src="{{ \Illuminate\Support\Facades\Storage::url($g->signature_image) }}" alt="亲笔签"
               style="max-height:90px;border:1px solid var(--ds-border-1);border-radius:6px;padding:6px;background:#fff;margin:8px 0;display:block">
        @endif
        <div class="mono text-xs muted">{{ $g->code }}</div>
        <div class="muted text-xs mt-2">— {{ $g->adoption?->user?->nickname ?? '云乡民' }} · 宁夏红寺堡枸杞田</div>
      </div>
    @endforeach
  </div>

  <style>
    @media print {
      .nav-admin, .no-print { display:none !important; }
      .main-admin { max-width:none; margin:0; padding:8px 16px; }
      .card-grid { grid-template-columns:repeat(2,1fr); gap:12px; }
    }
  </style>
@endsection