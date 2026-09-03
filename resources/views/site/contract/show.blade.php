@extends('layouts.site')

@section('title', '认养合同 · ' . $contract->contract_no)

@section('content')
  <div class="panel" style="max-width:720px;margin:0 auto">
    <div class="flex justify-between items-start mb-4">
      <div>
        <h1 style="font-size:var(--ds-h2);margin:0 0 4px">认养合同</h1>
        <p class="text-xs muted" style="margin:0">编号 {{ $contract->contract_no }} · 版本 {{ $contract->template_version }}</p>
      </div>
      <span class="tag tag-available">已签署</span>
    </div>

    <div class="sub-card mb-4" style="padding:14px 18px">
      <div class="text-xs muted mb-1" style="letter-spacing:.12em">认养人</div>
      <div class="text-sm">{{ $adoption->user->nickname ?? '云乡民' }} · 季节 {{ $adoption->season_year }}</div>
      <div class="text-xs muted mt-2" style="letter-spacing:.12em">认养单</div>
      <div class="text-sm">{{ $adoption->adoption_no }}</div>
    </div>

    <div class="rule-block">
      @foreach ($contract->clauses as $clause)
        <div class="rule-row" style="align-items:flex-start">
          <span class="rule-key">{{ $clause['title'] }}</span>
          <span>{{ $clause['body'] }}</span>
        </div>
      @endforeach
    </div>

    <div class="sub-card mt-4" style="padding:14px 18px;background:var(--ds-bg-layer-2)">
      <div class="text-xs muted mb-1" style="letter-spacing:.12em">签署留痕</div>
      <div class="text-sm">
        签署时间：{{ $contract->signed_at?->format('Y-m-d H:i') }}<br>
        签署 IP：{{ $contract->signed_ip ?? '—' }}
      </div>
    </div>

    <p class="note text-xs mt-4">
      本合同为电子合同，条款于签署时按版本 {{ $contract->template_version }} 快照锁定。
      如需纸质版，可使用浏览器「打印」功能另存 PDF。
    </p>

    <div class="flex gap-2 mt-4">
      <a class="btn btn-ghost btn-sm" href="javascript:window.print()">打印 / 另存 PDF</a>
      <a class="btn btn-ghost btn-sm" href="{{ route('tenant.my.plot', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">返回我的田</a>
    </div>
  </div>
@endsection
