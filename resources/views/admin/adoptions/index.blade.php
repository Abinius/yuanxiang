@extends('layouts.dashboard')

@section('title', '订单管理')

@section('content')
  <div class="page-header">
    <h1 class="page-title">订单管理</h1>
    <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}">刷新</a>
  </div>

  @if (session('status'))
    <div class="ok">{{ session('status') }}</div>
  @endif
  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  <div class="table-bar">
    <form method="GET" action="{{ route('tenant.admin.adoptions.index', ['tenant' => $tenant->slug]) }}" style="display:flex;align-items:center;gap:8px;flex:1;max-width:320px">
      <label class="text-sm" style="margin:0">状态</label>
      <select name="status" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">全部</option>
        @foreach (\App\Enums\AdoptionStatus::cases() as $s)
          <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
        @endforeach
      </select>
    </form>
    <div class="spacer"></div>
    <span class="note text-xs muted">共 {{ $adoptions->total() ?? 0 }} 条</span>
  </div>

  @if ($adoptions->isEmpty())
    <div class="empty">
      <div class="empty-icon">📋</div>
      <div>还没有认养订单。</div>
      <div class="empty-hint">点击「新建订单」添加第一条认养记录</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>订单号</th>
            <th>认养人</th>
            <th>田块</th>
            <th>方案</th>
            <th>金额</th>
            <th>状态</th>
            <th>支付</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($adoptions as $adoption)
            @php $paid = $adoption->payments->contains(fn ($p) => $p->status === \App\Enums\PaymentStatus::Paid); @endphp
            <tr>
              <td class="mono">{{ $adoption->adoption_no }}</td>
              <td>{{ $adoption->user?->nickname ?? '—' }}</td>
              <td>{{ $adoption->adoptable?->code ?? '—' }}</td>
              <td>{{ $adoption->plan?->name ?? '—' }}</td>
              <td>¥{{ number_format($adoption->annual_fee) }}</td>
              <td>
                <span class="tag tag-{{ $adoption->status->value === 'active' ? 'active' : ($paid ? 'adopted' : 'off') }}">
                  {{ $adoption->status->label() }}
                </span>
              </td>
              <td>{{ $adoption->payments->isNotEmpty() ? $adoption->payments->last()->status->label() : '—' }}</td>
              <td>
                <span class="note text-xs">详情见订单 {{ $adoption->adoption_no }}</span>
                @if ($paid)
                  <form method="POST" action="{{ route('tenant.admin.refund', ['tenant' => $tenant->slug, 'adoption' => $adoption->id]) }}" style="display:inline;margin-top:4px">
                    @csrf
                    <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('确认发起退款？')">退款</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="pager">
      {{ $adoptions->appends(request()->query())->links() }}
    </div>
  @endif
@endsection