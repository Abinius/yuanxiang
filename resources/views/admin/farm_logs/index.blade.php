@extends('layouts.dashboard')

@section('title', '农事内容管理')

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">农事内容管理</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  <div class="table-bar">
    <form method="GET" action="{{ route('tenant.admin.farm-logs.index', ['tenant' => $tenant->slug]) }}" style="display:flex;align-items:center;gap:8px;flex:1;max-width:280px">
      <label class="text-sm" style="margin:0">类型</label>
      <select name="type" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">全部</option>
        @foreach (\App\Enums\FarmLogType::cases() as $t)
          <option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>
        @endforeach
      </select>
      <noscript><button class="btn btn-ghost btn-sm" type="submit">筛选</button></noscript>
    </form>
    <div class="spacer"></div>
    <span class="note text-xs muted">共 {{ $logs->total() ?? 0 }} 条</span>
  </div>

  @if ($logs->isEmpty())
    <div class="empty">
      <div class="empty-icon">📝</div>
      <div>还没有农事记录。</div>
      <div class="empty-hint">由家人端或后台录入农事动态、直播预告等。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>类型</th><th>标题</th><th>田块</th><th>记录人</th>
            <th>时间</th><th>公开</th><th>溯源节点</th><th>操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($logs as $log)
            <tr>
              <td>{{ $log->type->label() }}</td>
              <td class="font-medium">{{ $log->title }}</td>
              <td>{{ $log->plot?->code ?? '—' }}</td>
              <td>{{ $log->author?->nickname ?? '—' }}</td>
              <td class="text-xs">{{ $log->occurred_at?->toDateString() ?? '—' }}</td>
              <td>
                <span class="tag tag-dot {{ $log->is_public ? 'tag-available' : 'tag-off' }}">
                  {{ $log->is_public ? '公开' : '私密' }}
                </span>
              </td>
              <td>
                <span class="tag tag-dot {{ $log->is_trace_node ? 'tag-renew' : 'tag-off' }}">
                  {{ $log->is_trace_node ? '溯源' : '—' }}
                </span>
              </td>
              <td>
                <form method="POST" action="{{ route('tenant.admin.farm-logs.destroy', ['tenant' => $tenant->slug, 'farm_log' => $log]) }}" style="display:inline" onsubmit="return confirm('删除该记录?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-danger btn-sm" type="submit">删除</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="pager">
      {{ $logs->appends(request()->query())->links() }}
    </div>
  @endif
@endsection