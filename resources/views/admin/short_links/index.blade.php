@extends('layouts.dashboard')

@section('title', '短链接')

@section('content')
  <div class="flex items-baseline justify-between mb-4" style="flex-wrap:wrap;gap:12px">
    <h1 class="hero-title" style="font-size:var(--ds-h2)">短链接</h1>
    <a class="btn btn-primary btn-sm" href="{{ route('tenant.admin.short-links.create', ['tenant' => $tenant->slug]) }}">+ 生成短链</a>
  </div>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif

  @if ($shortLinks->isEmpty())
    <div class="empty">
      <div class="empty-icon">🔗</div>
      <div>还没有短链。</div>
      <div class="empty-hint">点击「生成短链」创建 /u/{code} 跳转,可贴进分享卡片或物料二维码。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>短链</th><th>目标地址</th><th>点击</th><th>操作</th></tr>
        </thead>
        <tbody>
          @foreach ($shortLinks as $link)
            <tr>
              <td>
                <a class="font-medium text-brand mono" href="{{ route('tenant.short-link.redirect', ['tenant' => $tenant->slug, 'code' => $link->code]) }}">
                  /u/{{ $link->code }}
                </a>
              </td>
              <td class="mono text-xs" style="word-break:break-all">{{ $link->target_url }}</td>
              <td>
                <span class="tag tag-renew">{{ $link->click_count }}</span>
              </td>
              <td>
                <button class="btn btn-ghost btn-sm" type="button"
                        onclick="navigator.clipboard.writeText('{{ route('tenant.short-link.redirect', ['tenant' => $tenant->slug, 'code' => $link->code]) }}')">
                  复制
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection