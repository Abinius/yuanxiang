@extends('layouts.dashboard')

@section('title', '补退管理')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">缺产补/退管理</h1>
    <div class="flex items-center gap-2" style="flex-wrap:wrap">
      <form method="POST" action="{{ route('tenant.admin.adjustments.apply-all', ['tenant' => $tenant->slug]) }}" onsubmit="return confirm('批量应用当前年度所有待处理补退?refund 会走微信退款。')">
        @csrf
        <input class="select" type="number" name="season_year" min="2000" max="2100" value="{{ now()->year }}" required style="width:90px">
        <button class="btn btn-ghost btn-sm" type="submit">批量应用</button>
      </form>
      <form method="POST" action="{{ route('tenant.admin.adjustments.settle', ['tenant' => $tenant->slug]) }}">
        @csrf
        <div class="flex items-center gap-2">
          <label class="text-sm">结算年度</label>
          <input class="select" type="number" name="season_year" min="2000" max="2100" value="{{ now()->year }}" required style="width:90px">
          <button class="btn btn-primary btn-sm" type="submit">生成补退</button>
        </div>
      </form>
    </div>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <p class="note mb-5">
    按年度结算:欠收(低于保底)按 <b class="serif text-brand">¥150/kg</b> 折算退费;严重欠收另附下季优先认养权。平年/丰收不生成。
  </p>

  @if ($adjustments->isEmpty())
    <div class="empty">
      <div class="empty-icon">⚖️</div>
      <div>还没有补退记录。</div>
      <div class="empty-hint">选择年度后点击「生成补退」,系统按保底与实际采收量自动结算。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>认养单</th><th>认养人</th><th>田块</th><th>年度</th>
            <th>类型</th><th>金额</th><th>原因</th><th>状态</th><th>操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($adjustments as $a)
            <tr>
              <td class="mono">{{ $a->adoption?->adoption_no ?? '—' }}</td>
              <td>{{ $a->adoption?->user?->nickname ?? '—' }}</td>
              <td>{{ $a->adoption?->adoptable?->code ?? '—' }}</td>
              <td>{{ $a->season_year ?? '—' }}</td>
              <td>{{ \App\Enums\AdjustmentType::from($a->type)->label() }}</td>
              <td class="text-brand font-medium">@if ($a->amount) ¥{{ number_format($a->amount, 2) }} @else — @endif</td>
              <td class="text-xs">{{ $a->reason ?? '—' }}</td>
              <td>
                <span class="tag {{ $a->status === 'applied' ? 'tag-active' : 'tag-warn' }}">
                  {{ $a->status === 'applied' ? '已应用' : '待处理' }}
                </span>
              </td>
              <td>
                @if ($a->status === 'pending')
                  <form method="POST" action="{{ route('tenant.admin.adjustments.apply', ['tenant' => $tenant->slug, 'adjustment' => $a]) }}" style="display:inline" onsubmit="return confirm('应用该补退?refund 会走微信退款。')">
                    @csrf
                    <button class="btn btn-ghost btn-sm" type="submit">应用</button>
                  </form>
                @else
                  <span class="muted text-xs">已处理</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection