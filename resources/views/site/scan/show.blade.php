@extends('layouts.site')

@section('title', '溯源码 · '.$traceCode->code)

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header" style="text-align:center;justify-content:center;flex-direction:column;align-items:center">
      <div class="np-eyebrow" style="letter-spacing:0.18em">溯源认证 · 每箱一码</div>
      <h1 class="page-title serif text-brand" style="font-size:24px">{{ $traceCode->code }}</h1>
      <div class="text-sm muted">{{ $plot->code }} · 本箱已扫码 <b class="text-brand">{{ $traceCode->scanned_count }}</b> 次</div>
    </div>

    @if ($harvest)
      <div class="order-status">
        <div class="serif font-bold text-brand">采收批次 · {{ $harvest->season_year }} 年度</div>
        <div class="text-sm" style="color:var(--ds-text-mute);margin-top:6px;line-height:1.8">
          采收日期:{{ $harvest->harvested_at?->toDateString() }}<br>
          干重:<b class="text-brand">{{ $harvest->dry_weight_kg }}kg</b>
          @if ($harvest->quality_grade) · 等级:{{ $harvest->quality_grade }} @endif<br>
          @if ($harvest->notes) 备注:{{ $harvest->notes }} @endif
        </div>
      </div>
    @endif

    @if ($traceCode->adoption)
      <div class="sub-card mb-3" style="background:var(--ds-bg-layer-2);box-shadow:none;padding:10px 14px">
        <div class="text-sm">认养人:<b class="text-brand">{{ $traceCode->adoption->named_label }}</b></div>
      </div>
    @endif

    @include('site.partials.trace-timeline', ['nodes' => $nodes])

    <div class="mt-4">
      @include('site.partials.share', ['shareTitle' => $traceCode->code])
    </div>

    <div class="text-center mt-4">
      <a class="btn btn-primary btn-lg" href="{{ route('tenant.adopt.show', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">认养这块田</a>
    </div>
    <div class="text-center mt-3">
      <a href="{{ route('tenant.login', ['tenant' => $tenant->slug]) }}" class="btn btn-ghost btn-sm">成为云乡民,认养你的田 ›</a>
    </div>
    <p class="note text-xs text-center" style="margin-top:10px">
      本箱枸杞以有机肥(NXLB)投入品种植,检测合格。
    </p>
  </div>
@endsection