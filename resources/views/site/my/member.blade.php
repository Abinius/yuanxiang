@extends('layouts.site')

@section('title', '我的会员')

@section('content')
  <div class="panel" style="max-width:680px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">我的会员</h1>
      <a class="back-link" href="{{ route('tenant.my.index', ['tenant' => $tenant->slug]) }}">我的认养</a>
    </div>

    <div class="sub-card" style="padding:20px;margin-bottom:18px;text-align:center">
      <div class="text-xs muted">当前等级</div>
      <div class="serif font-bold text-brand" style="font-size:28px;margin:4px 0">{{ $member['label'] }}</div>
      <div class="text-sm muted">云乡民 · {{ $member['tier'] }}</div>
      @if ($member['member_since'])
        <div class="text-xs muted mt-1">升级于 {{ $member['member_since']->format('Y-m-d') }}</div>
      @endif
    </div>

    <div class="card-grid grid-3" style="margin-bottom:18px">
      <div class="card" style="padding:12px">
        <div class="text-xs muted">近365天消费</div>
        <div class="font-medium serif text-brand">¥{{ number_format($member['spend'], 2) }}</div>
      </div>
      <div class="card" style="padding:12px">
        <div class="text-xs muted">下一级门槛</div>
        <div class="font-medium">
          @if ($member['next_threshold'] !== null)
            ¥{{ number_format($member['next_threshold'], 2) }}
          @else
            已达最高级
          @endif
        </div>
      </div>
      <div class="card" style="padding:12px">
        <div class="text-xs muted">升级进度</div>
        <div class="font-medium">{{ round($member['progress'] * 100) }}%</div>
      </div>
    </div>

    @if ($member['next_threshold'] !== null && $member['progress'] < 1)
      <div class="sub-card" style="padding:14px;margin-bottom:18px">
        <div class="text-sm muted">再消费 <b class="text-brand">¥{{ number_format($member['next_threshold'] - $member['spend'], 2) }}</b> 即可升级</div>
      </div>
    @endif

    <h2 style="margin:18px 0 8px;font-size:var(--ds-h3)">当前权益</h2>
    <div class="sub-card" style="padding:14px">
      <p class="text-sm" style="margin:0;line-height:1.7">{{ $member['benefits'] }}</p>
    </div>
  </div>
@endsection
