@extends('layouts.site')

@section('title', $plot->code)

@section('content')
  <section class="section">
    <div class="flex items-baseline justify-between mb-3" style="flex-wrap:wrap;gap:8px">
      <h1 style="font-size:var(--ds-h2);margin:0">{{ $plot->code }} 拼团田 · 单株 ¥300/年</h1>
    </div>
    <p class="lede">共 {{ $plants->count() }} 株 · 池均摊产:按认养株数 × 池均单株产量交付</p>
  </section>

  <div class="product-grid">
    @foreach ($plants as $pl)
      @include('site.partials.plot-card', [
        'plot' => $pl,
        'price' => '¥300/年',
        'meta' => '单株 · 命名/监控/溯源',
      ])
    @endforeach
  </div>
@endsection