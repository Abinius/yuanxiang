@extends('layouts.site')

@section('title', '礼盒祝福')

@section('content')
  <div class="panel" style="max-width:560px;margin:0 auto;text-align:center">
    <div class="np-eyebrow" style="letter-spacing:0.18em;font-size:var(--ds-body-xs);color:var(--ds-text-mute);font-weight:500">
      来自光彩云村庄 · 云乡民的节日礼盒
    </div>
    <h1 style="font-size:var(--ds-h2);margin:10px 0">{{ $giftBox->festival->label() }}祝福{{ $giftBox->year }}</h1>

    @if ($giftBox->recipient_name)
      <p style="font-size:var(--ds-body);color:var(--ds-text);margin-bottom:12px">致 {{ $giftBox->recipient_name }}</p>
    @endif

    @if ($giftBox->message)
      <p class="serif" style="font-size:var(--ds-body);line-height:1.9;color:var(--ds-text)">{{ $giftBox->message }}</p>
    @endif

    @if ($giftBox->signature_image)
      <div class="mt-4">
        <div class="text-xs muted" style="margin-bottom:6px">亲笔签</div>
        <img src="{{ \Illuminate\Support\Facades\Storage::url($giftBox->signature_image) }}"
             alt="亲笔签"
             style="max-height:140px;border:1px solid var(--ds-border-1);border-radius:var(--ds-r-md);padding:10px;background:#fff;display:block;margin:0 auto">
      </div>
    @endif

    <p class="text-sm" style="color:var(--ds-text-mute);line-height:1.7;margin-top:16px">
      这份礼盒来自 {{ $giver?->nickname ?? '一位云乡民' }} 认养的宁夏红寺堡枸杞田。生态种植,看得见的田。
    </p>

    <div style="margin-top:16px">
      @include('site.partials.share', ['shareTitle' => $giftBox->festival->label().'祝福'])
    </div>

    <div class="mt-4">
      <a class="btn btn-primary btn-lg" href="{{ route('tenant.login', ['tenant' => $tenant->slug]) }}">成为云乡民,认养你的田 ›</a>
    </div>
  </div>
@endsection