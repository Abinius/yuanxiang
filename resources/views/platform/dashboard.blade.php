@extends('layouts.dashboard')

@section('title', '平台后台')
@section('nav_right')
  <span class="user">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('platform.logout') }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  <div class="hero-bar">
    <div>
      <h1 class="hero-title">平台后台</h1>
      <p class="lede">租户总览 · 共 {{ $tenants->count() }} 个站点</p>
    </div>
  </div>

  @forelse ($tenants as $t)
    <div class="table-wrap mb-4">
      <table>
        <thead>
          <tr><th>ID</th><th>Slug</th><th>名称</th><th>基地</th><th>方案</th><th>状态</th></tr>
        </thead>
        <tbody>
          <tr>
            <td class="mono">{{ $t->id }}</td>
            <td class="mono">{{ $t->slug }}</td>
            <td class="font-medium">{{ $t->name }}</td>
            <td>{{ $t->farms_count }}</td>
            <td>{{ $t->plans_count }}</td>
            <td>
              <span class="tag tag-dot {{ $t->status->value === 'active' ? 'tag-available' : 'tag-off' }}">
                {{ $t->status->value }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  @empty
    <div class="empty">
      <div class="empty-icon">🏘️</div>
      <div>暂无租户</div>
      <div class="empty-hint">先开通一个站点再回来管理。</div>
    </div>
  @endforelse

  <p class="note text-xs" style="margin-top:8px">
    租户开通 / 套餐 / 计费 / 聚合看板:V2 完善。
  </p>
@endsection