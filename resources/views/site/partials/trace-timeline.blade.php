@php
  $kindColors = [
    'fertilize'    => '#5F7A54',
    'harvest'      => '#B33A26',
    'harvest_note' => '#B33A26',
    'inspect'      => '#8a8378',
    'detection'    => '#2E6F95',
  ];
@endphp

<div class="trace-timeline">
  @forelse ($nodes as $node)
    @php $c = $kindColors[$node['kind']] ?? 'var(--color-brand-500)'; @endphp
    <div class="tl-node">
      <span class="tl-dot" style="background:{{ $c }};box-shadow:0 0 0 2px {{ $c }}22"></span>

      <div class="tl-head">
        <span class="tag" style="background:var(--color-brand-50);color:{{ $c }}">{{ $node['badge'] }}</span>
        <span class="text-xs muted">{{ $node['date'] }}</span>
        @if (!empty($node['plot_code']))
          <span class="text-xs muted">· {{ $node['plot_code'] }}</span>
        @endif
      </div>

      <div class="tl-title">{{ $node['title'] }}</div>
      @if (!empty($node['content']))
        <p class="text-sm" style="color:var(--ds-text-soft);margin:0 0 4px">{{ $node['content'] }}</p>
      @endif

      @if ($node['kind'] === 'harvest')
        <div class="text-xs muted mt-1">
          干重 {{ $node['extra']['dry_weight_kg'] }}kg
          @if (!empty($node['extra']['quality_grade'])) · 等级 {{ $node['extra']['quality_grade'] }} @endif
        </div>
      @endif

      @if (!empty($node['extra']['note_title']))
        <div class="tl-info mt-1">
          <span class="font-medium">家人记录</span>:<b class="font-medium" style="color:var(--ds-text)">{{ $node['extra']['note_title'] }}</b>
          @if (!empty($node['extra']['note'])) — {{ $node['extra']['note'] }} @endif
        </div>
      @endif

      @if (!empty($node['extra']['batch']))
        @php $b = $node['extra']['batch']; @endphp
        <div class="tl-batch">
          <div class="font-bold" style="color:#5F7A54">有机肥批次 · {{ $b['batch_no'] }}</div>
          <div style="color:var(--ds-text-mute);margin-top:4px;line-height:1.8">
            @if ($b['produced_at']) 生产:{{ $b['produced_at'] }}<br>@endif
            @if ($b['nxlb_ref']) NXLB 参考:{{ $b['nxlb_ref'] }}<br>@endif
            @if ($b['ingredients']) 成分:{{ $b['ingredients'] }}<br>@endif
            @if ($b['test_report_url'])
              检测:<a href="{{ $b['test_report_url'] }}" target="_blank" rel="noopener" style="color:var(--color-brand-500)">查看检测报告</a>
            @endif
          </div>
        </div>
      @endif

      @if ($node['kind'] === 'detection')
        <div class="tl-detection">
          <div class="font-bold" style="color:#2E6F95">
            {{ $node['extra']['org'] ?? '检测机构' }}
            @if (array_key_exists('qualified', $node['extra']))
              <span class="tag" style="margin-left:6px;background:{{ $node['extra']['qualified'] ? 'var(--color-ok-bg)' : 'var(--color-danger-bg)' }};color:{{ $node['extra']['qualified'] ? 'var(--color-available-500)' : 'var(--color-danger)' }}">
                {{ $node['extra']['qualified'] ? '合格' : '不合格' }}
              </span>
            @endif
          </div>
          @if (!empty($node['extra']['result_summary']))
            <div class="text-xs" style="color:var(--ds-text-mute);margin-top:4px;line-height:1.8">
              @foreach ($node['extra']['result_summary'] as $k => $v) {{ $k }}:{{ $v }}; @endforeach
            </div>
          @endif
          @if (!empty($node['extra']['report_url']))
            <a href="{{ $node['extra']['report_url'] }}" target="_blank" rel="noopener" style="color:var(--color-brand-500)">查看报告原文</a>
          @endif
        </div>
      @endif

      @if (!empty($node['images']))
        <div class="tl-images">
          @foreach ($node['images'] as $img)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($img) }}" alt="" loading="lazy">
          @endforeach
        </div>
      @endif

      @if (!empty($node['author']))
        <div class="text-xs muted mt-1">记录人:{{ $node['author'] }}</div>
      @endif
    </div>
  @empty
    <p class="note">溯源时间线建设中,家人记录会在采收季陆续更新。</p>
  @endforelse
</div>