@extends('layouts.site')

@section('title', '定制礼盒')

@section('content')
  <div class="panel" style="max-width:560px;margin:0 auto">
    <h1 style="font-size:var(--ds-h2);margin:0 0 16px">定制节日礼盒</h1>

    @if ($festivals->isEmpty())
      <div class="alert">本年度礼盒额度已用完。</div>
    @else
      <form method="POST" action="{{ route('tenant.my.gift.store', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">
        @csrf
        <div class="field">
          <label>选择节日(剩余额度)</label>
          <select name="festival" class="select" required>
            @foreach ($festivals as $f)
              <option value="{{ $f['value'] }}">{{ $f['label'] }}(剩 {{ $f['remaining'] }} 盒)</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">下一步:定制</button>
      </form>
    @endif

    <p class="note text-xs" style="margin-top:14px">
      礼盒用认养权益抵扣,不另收费;内容随节令(中秋新果 / 春节端午存果与加工品)。
    </p>
  </div>
@endsection