@extends('layouts.site')

@section('title', '认养田地')

@section('content')
  <section class="section">
    <div class="section-title">
      <span>分地档 · 一分地 ¥5000/年(50 块)</span>
    </div>
    <div class="product-grid">
      @foreach ($plots as $p)
        <a class="product-card" href="{{ route('tenant.adopt.show', ['tenant' => $tenant->slug, 'plot' => $p]) }}">
          <div class="code">{{ $p->code }}</div>
          <div class="price">¥{{ number_format($p->price_yearly) }}/年</div>
          <div class="meta">{{ $p->mu_area }} 亩 · 命名/监控/溯源/礼盒</div>
          <div class="mt-2">
            <span class="tag {{ $p->status->value === 'available' ? 'tag-available' : ($p->status->value === 'adopted' ? 'tag-adopted' : 'tag-off') }}">
              {{ $statusLabels[$p->status->value] }}
            </span>
          </div>
        </a>
      @endforeach
    </div>
  </section>

  <section class="section">
    <div class="section-title">
      <span>株档 · 单株 ¥300/年(10 片拼团田)</span>
    </div>
    <div class="product-grid">
      @foreach ($groups as $g)
        <a class="product-card" href="{{ route('tenant.adopt.show', ['tenant' => $tenant->slug, 'plot' => $g]) }}">
          <div class="code">{{ $g->code }}</div>
          <div class="price">¥300/株</div>
          <div class="meta">{{ $g->children_count }} 株可认养 · 池均摊</div>
        </a>
      @endforeach
    </div>
  </section>
@endsection