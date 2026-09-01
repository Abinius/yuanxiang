@extends('layouts.site')

@section('title', $adoption->named_label ?: '我的田')

@section('content')
  <div class="panel" style="max-width:560px;margin:0 auto;text-align:center">
    @include('site.partials.nameplate', ['adoption' => $adoption, 'shareable' => true])

    <div class="mt-4">
      @include('site.partials.share', ['shareUrl' => $shareUrl, 'shareTitle' => $adoption->named_label ?: '我的田'])
    </div>

    <div class="text-center mt-3">
      <a class="btn btn-primary btn-lg" href="{{ route('tenant.adopt.show', ['tenant' => $tenant->slug, 'plot' => $adoption->adoptable]) }}">认养这块田 ›</a>
    </div>
    <p class="note text-xs text-center" style="margin-top:10px">宁夏红寺堡 · 生态种植 · 全程可溯源</p>
  </div>
@endsection