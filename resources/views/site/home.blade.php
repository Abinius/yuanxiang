@extends('layouts.site')

@section('content')
  <section class="section">
    <div class="hero-bar">
      <div>
        <h1 class="hero-title">认养一块真实的宁夏枸杞田</h1>
        <p class="lede">这里是 {{ $tenant->name }}。认养后可在「我的田」查看生长日历、农事动态与配送进度；实时监控与溯源时间线均已上线。</p>
      </div>
      <div class="hero-stats">
        <a class="btn btn-primary" href="{{ route('tenant.adopt.index', ['tenant' => $tenant->slug]) }}">去看田块</a>
        <a class="btn btn-ghost" href="{{ route('tenant.live.index', ['tenant' => $tenant->slug]) }}">实时监控</a>
      </div>
    </div>

    <p class="note mb-6">
      <span class="serif" style="color:var(--color-brand-500);font-weight:600">云乡民认养 · 从 6 亩样板田起步</span>
      <span class="muted"> · 一颗枸杞从播种到交付,全程可查</span>
    </p>
  </section>

  <section class="section">
    <div class="section-title">为什么是云乡</div>
    <div class="card-grid grid-3">
      <div class="card">
        <div class="num sm serif text-brand">真实田块</div>
        <div class="label mb-3">每一块田都有独立编号与坐标,扫码即可追溯。</div>
        <div class="note text-xs">不是概念,是可认养、可查、可采的 6 亩样板田。</div>
      </div>
      <div class="card">
        <div class="num sm serif text-brand">家人直供</div>
        <div class="label mb-3">本地种植户亲自管理,农事动态、有机肥、采收一一录入。</div>
        <div class="note text-xs">红寺堡本地劳力 + 自有有机肥厂,全程在地。</div>
      </div>
      <div class="card">
        <div class="num sm serif text-brand">全程溯源</div>
        <div class="label mb-3">从播种、施肥、采收、装箱到配送,时间线可查。</div>
        <div class="note text-xs">每箱枸杞配专属溯源码,扫码即见生长故事。</div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="section-title">认养流程</div>
    <div class="panel">
      <div class="flow-steps">
        <div class="flow-step">
          <div class="flow-num">01</div>
          <div class="flow-label">选田块</div>
          <div class="flow-note">浏览可认养方案,选定一片田</div>
        </div>
        <div class="flow-step">
          <div class="flow-num">02</div>
          <div class="flow-label">下单认养</div>
          <div class="flow-note">支付年费,成为云乡民</div>
        </div>
        <div class="flow-step">
          <div class="flow-num">03</div>
          <div class="flow-label">看生长</div>
          <div class="flow-note">查农事动态与实时监控</div>
        </div>
        <div class="flow-step">
          <div class="flow-num">04</div>
          <div class="flow-label">收枸杞</div>
          <div class="flow-note">采收后配送,扫码溯源</div>
        </div>
      </div>
      <hr class="divider">
      <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px">
        <p class="note text-sm">
          <span class="eyebrow">认养即共建</span>
          <span>你的田块会成为村庄平台样板田的一部分,带动本地种植户一起把好枸杞做出来。</span>
        </p>
        <a class="btn btn-primary btn-sm" href="{{ route('tenant.adopt.index', ['tenant' => $tenant->slug]) }}">去认养 →</a>
      </div>
    </div>
  </section>

  <section class="section">
    <details class="details">
      <summary>常见问题</summary>
      <div class="flex flex-col gap-3" style="margin-top:12px">
        <div>
          <div class="font-medium text-sm">认养多久?</div>
          <div class="note text-xs">按年认养,一季一季看田长大,到期可续费。</div>
        </div>
        <div>
          <div class="font-medium text-sm">能自己去看吗?</div>
          <div class="note text-xs">可以,基地在宁夏红寺堡,欢迎实地走访。</div>
        </div>
        <div>
          <div class="font-medium text-sm">枸杞怎么交付?</div>
          <div class="note text-xs">采收后统一装箱配送,每箱配溯源码。</div>
        </div>
      </div>
    </details>
  </section>

  <footer class="site-footer">
    {{ config('site.defaults.footer_copyright', '宁夏花乌巷食品有限公司') }} · 光彩云村庄
  </footer>
@endsection