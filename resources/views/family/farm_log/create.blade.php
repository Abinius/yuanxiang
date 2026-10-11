@extends('layouts.dashboard')
@php($log = $farmLog ?? null)

@section('title', '发动态 / 直播预告')

@section('nav_right')
  @include('family.partials.nav')
@endsection

@section('content')
  <h2 class="admin-h1 mb-4">发农事动态 / 直播预告</h2>
  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif
  <form class="form-single" method="POST"
        action="{{ $log?->exists ? route('tenant.family.logs.update', ['farm_log' => $log]) : route('tenant.family.logs.store', []) }}">
    @csrf
    <div class="field">
      <label>田块</label>
      <select class="select" name="plot_id" required>
        @foreach ($plots as $plot)
          <option value="{{ $plot->id }}" @selected(old('plot_id', $log?->plot_id) == $plot->id)>{{ $plot->code }}</option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label>类型</label>
      <select class="select" name="type" required>
        @foreach ($types as $t)
          <option value="{{ $t['value'] }}" @selected(old('type', $log?->type?->value ?? request('type')) === $t['value'])>{{ $t['label'] }}</option>
        @endforeach
      </select>
    </div>

    <div class="field" id="batch-field" style="display:none">
      <label>有机肥批次（NXLB，施肥时选）</label>
      <select class="select" name="fertilizer_batch_id">
        <option value="">（可选）</option>
        @forelse ($batches as $batch)
          <option value="{{ $batch->id }}" @selected(old('fertilizer_batch_id', $log?->fertilizer_batch_id) == $batch->id)>
            {{ $batch->batch_no }}{{ $batch->produced_at ? '（'.$batch->produced_at->format('Y-m-d').'）' : '' }}
          </option>
        @empty
          <option value="">暂无批次，请先在「录有机肥批次」录入</option>
        @endforelse
      </select>
    </div>

    @if ($log?->video_url)
      <div class="field">
        <label>已上传解说视频</label>
        <p class="muted text-xs">{{ str_replace('storage/', '/storage/', $log->video_url) }}（{{ $log->payload['duration'] ?? 0 }}s）·重新选择文件即替换</p>
      </div>
    @endif

    <div class="field" id="video-field" style="display:none">
      <label>露脸解说视频（≤60s，mp4/mov/webm，≤40MB）</label>
      <input class="input" type="file" name="video_url" accept="video/*">
      <div class="field" style="margin-top:8px">
        <label>解说时长（秒）</label>
        <input class="input" type="number" name="video_duration" min="1" max="120" value="{{ old('video_duration', $log?->video_duration) }}">
      </div>
      <p class="note text-xs" style="margin-top:6px">录一段露脸解说，云乡民在我的田时间线直接可见。</p>
    </div>

    <div class="field">
      <label>标题(留空自动生成)</label>
      <input class="input" name="title" maxlength="60" value="{{ old('title', $log?->title) }}">
    </div>

    <div class="field">
      <label>内容</label>
      <textarea class="textarea" name="content" rows="4" maxlength="1000">{{ old('content', $log?->content) }}</textarea>
    </div>

    <div class="field">
      <label>图片（可选，最多 6 张，jpg/png/webp ≤4MB）</label>
      <input class="input" type="file" name="images[]" accept="image/*" multiple>
      @if (($log?->images) ?? [])
        <p class="muted text-xs mt-1">已有 {{ count($log->images) }} 张，重新选择即整体替换</p>
      @endif
    </div>

    <div class="field">
      <label>发生时间</label>
      <input class="input" type="date" name="occurred_at" value="{{ old('occurred_at', $log?->occurred_at?->toDateString() ?? now()->toDateString()) }}">
    </div>

    <label style="display:flex;align-items:center;gap:8px;margin-top:14px">
      <input type="checkbox" name="is_public" value="1" style="width:auto" @checked(old('is_public', $log?->is_public ?? true))>
      <span>对云乡民可见（默认公开，不勾选则仅家人端可见）</span>
    </label>

    <button class="btn btn-primary" type="submit" style="width:100%;margin-top:16px">{{ $log?->exists ? '保存修改' : '发布' }}</button>
  </form>
  <script>
    (function () {
      var sel = document.querySelector('select[name="type"]');
      var box = document.getElementById('batch-field');
      var vbox = document.getElementById('video-field');
      if (!sel) return;
      function toggle() {
        if (box) box.style.display = sel.value === 'fertilize' ? 'block' : 'none';
        if (vbox) vbox.style.display = sel.value === 'explain' ? 'block' : 'none';
      }
      sel.addEventListener('change', toggle);
      toggle();
    })();
  </script>
  <p class="note" style="margin-top:14px">直播预告请选"直播预告"类型；露脸解说选"解说"并上传≤60s 视频；开播提醒由后续模板消息推送。</p>
@endsection
