@extends('layouts.dashboard')

@section('title', '摄像头管理')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">摄像头管理</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.cameras.create', ['tenant' => $tenant->slug]) }}">+ 添加摄像头</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  <p class="note mb-5">
    真实流待 P3 摄像头到位;在此填 <span class="font-medium">萤石 / 阿里云</span> 的 stream_url 与 token 即可上线,无需改码。
  </p>

  @if ($cameras->isEmpty())
    <div class="empty">
      <div class="empty-icon">📹</div>
      <div>还没有摄像头。</div>
      <div class="empty-hint">点击「添加摄像头」录入一台萤石 / 阿里云直播设备。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>名称</th><th>设备号</th><th>田块</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
          @foreach ($cameras as $camera)
            <tr>
              <td class="font-medium">{{ $camera->name }}</td>
              <td class="mono">{{ $camera->device_no }}</td>
              <td>{{ $camera->plot?->code ?? '—' }}</td>
              <td>
                <span class="tag tag-dot {{ $camera->status === 'online' ? 'tag-available' : 'tag-off' }}">
                  {{ $camera->status === 'online' ? '在线' : '离线' }}
                </span>
              </td>
              <td class="flex gap-2">
                <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.cameras.edit', ['tenant' => $tenant->slug, 'camera' => $camera]) }}">编辑</a>
                <form method="POST" action="{{ route('tenant.admin.cameras.destroy', ['tenant' => $tenant->slug, 'camera' => $camera]) }}" onsubmit="return confirm('删除该摄像头?')">
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
  @endif
@endsection