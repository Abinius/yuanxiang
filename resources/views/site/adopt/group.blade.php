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
      <a class="product-card" href="{{ route('tenant.adopt.show', ['tenant' => $tenant->slug, 'plot' => $pl]) }}">
        <div class="code">{{ $pl->code }}</div>
        <div class="price">¥300/年</div>
        <div class="meta">单株 · 命名/监控/溯源</div>
        <div class="mt-2">
          <span class="tag {{ $pl->status->value === 'available' ? 'tag-available' : ($pl->status->value === 'adopted' ? 'tag-adopted' : 'tag-off') }}">
            {{ $statusLabels[$pl->status->value] }}
          </span>
        </div>
      </a>
    @endforeach
  </div>
@endsection