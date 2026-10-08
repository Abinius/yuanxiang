@extends('layouts.site')

@php
  $agreement = $agreements['items'][$key];
  $others = array_diff_key($agreements['items'] ?? [], [$key => null]);
@endphp

@section('title', $agreement['title'])

@section('content')
<div class="panel" style="max-width:760px;margin:0 auto">
  <h1 style="font-size:var(--ds-h2);margin:0 0 6px">{{ $agreement['title'] }}</h1>
  <p class="text-xs muted" style="margin:0">
    版本 {{ $agreements['version'] ?? '—' }} · 生效日期 {{ $agreements['effective'] ?? '—' }}
    · 运营主体：{{ config('site.defaults.footer_copyright') }}
  </p>

  <hr class="divider">
  @foreach ($agreement['sections'] as $section)
    <h2 style="font-size:var(--ds-h3);margin:22px 0 8px">{{ $section['title'] }}</h2>
    @foreach ($section['body'] as $para)
      <p style="margin-bottom:8px;line-height:1.9;color:var(--ds-text)">{{ $para }}</p>
    @endforeach
  @endforeach

  @if ($others)
    <hr class="divider">
    <p class="note text-xs" style="margin:16px 0 0">
      相关协议：@foreach ($others as $otherKey => $other)
        <a href="{{ route('tenant.agreement.show', ['key' => $otherKey]) }}">{{ $other['short'] }}</a>{{ $loop->last ? '' : ' ·' }}
      @endforeach
    </p>
  @endif

  <p class="note text-xs" style="margin:12px 0 0">如需纸质版，可使用浏览器「打印」功能另存 PDF。</p>
</div>
@endsection
