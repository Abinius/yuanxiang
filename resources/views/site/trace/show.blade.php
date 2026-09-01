@extends('layouts.site')

@section('title', $plot->code.' · 溯源')

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">溯源 · {{ $plot->code }}</h1>
    </div>
    <p class="note text-xs" style="margin-bottom:16px">
      从基施到采收,关键农事节点全程留痕:NXLB 有机肥批次、农事记录、检测报告,均由在地家人实地录入。
    </p>

    @include('site.partials.trace-timeline', ['nodes' => $nodes])

    <div style="margin-top:16px">
      @include('site.partials.share', ['shareTitle' => '溯源 · '.$plot->code])
    </div>
    <div class="text-center mt-4">
      <a class="btn btn-primary btn-lg" href="{{ route('tenant.adopt.show', ['tenant' => $tenant->slug, 'plot' => $plot]) }}">认养这块田</a>
    </div>
  </div>
@endsection