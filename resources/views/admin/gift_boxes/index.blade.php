@extends('layouts.dashboard')

@section('title', '礼盒管理')

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">礼盒管理</h1>

  @if (session('ok'))
    <div class="ok">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  @if ($giftBoxes->isEmpty())
    <div class="empty">
      <div class="empty-icon">🎁</div>
      <div>还没有礼盒。</div>
      <div class="empty-hint">由云乡民在「我的田」自定义贺卡,或由后台录入。</div>
    </div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>礼盒</th><th>认养人</th><th>收礼人</th>
            <th>状态</th><th>运单号</th><th>操作</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($giftBoxes as $g)
            <tr>
              <td>
                <span class="font-medium">{{ $g->festival->label() }} {{ $g->year }}</span>
                <br><span class="mono text-xs muted">{{ $g->code }}</span>
              </td>
              <td>{{ $g->adoption?->user?->nickname ?? '—' }}</td>
              <td class="text-xs">
                {{ $g->recipient_name ?? '未填写' }}
                @if ($g->recipient_phone)
                  <span class="muted">({{ $g->recipient_phone }})</span>
                @endif
              </td>
              <td>
                <span class="tag {{ $g->status->value === 'delivered' ? 'tag-available' : ($g->status->value === 'shipped' ? 'tag-renew' : ($g->status->value === 'making' ? 'tag-warn' : 'tag-off')) }}">
                  {{ $g->status->label() }}
                </span>
              </td>
              <td class="mono text-xs">{{ $g->tracking_no ?? '—' }}</td>
              <td>
                <div class="flex gap-1" style="flex-wrap:wrap">
                  <a class="btn btn-ghost btn-sm" href="{{ route('tenant.admin.gift-boxes.print', ['tenant' => $tenant->slug, 'ids' => $g->id]) }}">贺卡</a>
                  @if ($g->status->value === 'draft')
                    <form method="POST" action="{{ route('tenant.admin.gift-boxes.making', ['tenant' => $tenant->slug, 'giftBox' => $g]) }}">
                      @csrf
                      <button class="btn btn-primary btn-sm" type="submit">制作</button>
                    </form>
                  @endif
                  @if (in_array($g->status->value, ['draft', 'making']))
                    <form method="POST" action="{{ route('tenant.admin.gift-boxes.ship', ['tenant' => $tenant->slug, 'giftBox' => $g]) }}" class="flex gap-1">
                      @csrf
                      <input class="input" name="tracking_no" placeholder="运单号" required style="width:100px;font-size:11px;padding:5px 7px">
                      <input class="input" name="carrier" placeholder="承运商" style="width:62px;font-size:11px;padding:5px 7px">
                      <button class="btn btn-primary btn-sm" type="submit">发货</button>
                    </form>
                  @endif
                  @if ($g->status->value === 'shipped')
                    <form method="POST" action="{{ route('tenant.admin.gift-boxes.delivered', ['tenant' => $tenant->slug, 'giftBox' => $g]) }}">
                      @csrf
                      <button class="btn btn-soft btn-sm" type="submit">送达</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection