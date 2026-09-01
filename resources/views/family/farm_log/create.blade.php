@extends('layouts.dashboard')

@section('title', '发动态 / 直播预告')

@section('nav_right')
  <a href="{{ route('tenant.family.dashboard', ['tenant' => $tenant->slug]) }}" style="margin-right:16px">家人端</a>
  @if (auth()->user()->role->value === 'tenant_admin')
    <a href="{{ route('tenant.admin.dashboard', ['tenant' => $tenant->slug]) }}" style="margin-right:16px">管理后台</a>
  @endif
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}" style="margin-right:16px">前台</a>
  <span class="muted">{{ auth()->user()->nickname }}</span>
  <form method="POST" action="{{ route('tenant.logout', ['tenant' => $tenant->slug]) }}" style="display:inline">
    @csrf
    <button type="submit">退出</button>
  </form>
@endsection

@section('content')
  <h2>发农事动态 / 直播预告</h2>
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif
  <form method="POST" action="{{ route('tenant.family.logs.store', ['tenant' => $tenant->slug]) }}" enctype="multipart/form-data">
    @csrf
    <label>田块</label>
    <select name="plot_id" required>
      @foreach ($plots as $plot)
        <option value="{{ $plot->id }}" @selected(old('plot_id') == $plot->id)>{{ $plot->code }}</option>
      @endforeach
    </select>

    <label>类型</label>
    <select name="type" required>
      @foreach ($types as $t)
        <option value="{{ $t['value'] }}" @selected(old('type') === $t['value'])>{{ $t['label'] }}</option>
      @endforeach
    </select>

    <div id="batch-field" style="display:none">
      <label>有机肥批次（NXLB，施肥时选）</label>
      <select name="fertilizer_batch_id">
        <option value="">（可选）</option>
        @forelse ($batches as $batch)
          <option value="{{ $batch->id }}" @selected(old('fertilizer_batch_id') == $batch->id)>
            {{ $batch->batch_no }}{{ $batch->produced_at ? '（'.$batch->produced_at->format('Y-m-d').'）' : '' }}
          </option>
        @empty
          <option value="">暂无批次，请先在「录有机肥批次」录入</option>
        @endforelse
      </select>
    </div>

    <label>标题</label>
    <input name="title" required maxlength="60" value="{{ old('title') }}">

    <label>内容</label>
    <textarea name="content" rows="4" maxlength="1000">{{ old('content') }}</textarea>

    <label>图片（可选，最多 6 张，jpg/png/webp ≤4MB）</label>
    <input type="file" name="images[]" accept="image/*" multiple>

    <label>发生时间</label>
    <input type="date" name="occurred_at" value="{{ old('occurred_at', now()->toDateString()) }}">

    <label style="display:flex;align-items:center;gap:8px;margin-top:14px">
      <input type="hidden" name="is_public" value="0">
      <input type="checkbox" name="is_public" value="1" style="width:auto" @checked(old('is_public', '1') == '1')>
      <span>对云乡民可见（默认公开）</span>
    </label>

    <button class="btn" type="submit" style="width:100%;margin-top:16px">发布</button>
  </form>
  <script>
    (function () {
      var sel = document.querySelector('select[name="type"]');
      var box = document.getElementById('batch-field');
      if (!sel || !box) return;
      function toggle() { box.style.display = sel.value === 'fertilize' ? 'block' : 'none'; }
      sel.addEventListener('change', toggle);
      toggle();
    })();
  </script>
  <p class="note" style="margin-top:14px">直播预告请选"直播预告"类型；开播提醒由后续模板消息推送。</p>
@endsection
