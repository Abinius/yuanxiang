@extends('layouts.site')

@section('content')
  <?php
    // 文案出厂默认在 config('site.defaults.homepage')，后台可覆盖；输出全走 {{ }} 转义，不做富文本。
    $lede = str_replace('{tenant}', (string) $tenant->name, $home['hero_lede'] ?? '');
  ?>

  <section class="section">
    <div class="hero-bar">
      <div>
        <h1 class="hero-title">{{ $home['hero_title'] ?? '' }}</h1>
        <p class="lede">{{ $lede }}</p>
      </div>
      <div class="hero-stats">
        <a class="btn btn-primary" href="{{ route('tenant.adopt.index', []) }}">去看田块</a>
        <a class="btn btn-ghost" href="{{ route('tenant.live.index', []) }}">实时监控</a>
      </div>
    </div>

    @if (($home['eyebrow_primary'] ?? '') !== '' || ($home['eyebrow_secondary'] ?? '') !== '')
      <p class="note mb-6">
        @if (($home['eyebrow_primary'] ?? '') !== '')<span class="serif" style="color:var(--color-brand-500);font-weight:600">{{ $home['eyebrow_primary'] }}</span>@endif
        @if (($home['eyebrow_secondary'] ?? '') !== '')<span class="muted"> · {{ $home['eyebrow_secondary'] }}</span>@endif
      </p>
    @endif
  </section>

  @if (($home['show_points'] ?? false) && ! empty($home['points']))
    <section class="section">
      <div class="section-title">{{ $home['points_title'] ?? '' }}</div>
      <div class="card-grid grid-3">
        @foreach ($home['points'] as $p)
          <div class="card">
            <div class="num sm serif text-brand">{{ $p['title'] ?? '' }}</div>
            <div class="label mb-3">{{ $p['body'] ?? '' }}</div>
            <div class="note text-xs">{{ $p['note'] ?? '' }}</div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  @if (($home['show_flow'] ?? false) && ! empty($home['flow_steps']))
    <section class="section">
      <div class="section-title">{{ $home['flow_title'] ?? '' }}</div>
      <div class="panel">
        <div class="flow-steps">
          @foreach ($home['flow_steps'] as $step)
            <div class="flow-step">
              <div class="flow-num">{{ $step['num'] ?? '' }}</div>
              <div class="flow-label">{{ $step['label'] ?? '' }}</div>
              <div class="flow-note">{{ $step['note'] ?? '' }}</div>
            </div>
          @endforeach
        </div>
        <hr class="divider">
        @if (($home['cta_note_body'] ?? '') !== '' || ($home['cta_button'] ?? '') !== '')
          <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px">
            @if (($home['cta_note_body'] ?? '') !== '')
              <p class="note text-sm">
                @if (($home['cta_note_eyebrow'] ?? '') !== '')<span class="eyebrow">{{ $home['cta_note_eyebrow'] }}</span>@endif
                <span>{{ $home['cta_note_body'] }}</span>
              </p>
            @endif
            @if (($home['cta_button'] ?? '') !== '')
              <a class="btn btn-primary btn-sm" href="{{ route('tenant.adopt.index', []) }}">{{ $home['cta_button'] }}</a>
            @endif
          </div>
        @endif
      </div>
    </section>
  @endif

  @if (($home['show_faq'] ?? false) && ! empty($home['faq']))
    <section class="section">
      <details class="details">
        <summary>{{ $home['faq_title'] ?? '' }}</summary>
        <div class="flex flex-col gap-3" style="margin-top:12px">
          @foreach ($home['faq'] as $item)
            <div>
              <div class="font-medium text-sm">{{ $item['q'] ?? '' }}</div>
              <div class="note text-xs">{{ $item['a'] ?? '' }}</div>
            </div>
          @endforeach
        </div>
      </details>
    </section>
  @endif
@endsection
