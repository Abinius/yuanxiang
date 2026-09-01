@php
    $shareable = $shareable ?? false;
@endphp
<div class="nameplate {{ $shareable ? 'center' : '' }}">
    <div class="np-eyebrow">光彩云村庄 · 云乡民</div>
    <div class="np-label">{{ $adoption->named_label ?: '未命名' }}</div>
    <div class="np-meta">{{ $adoption->adoptable->code ?? '—' }} · {{ $adoption->season_year }} 年度</div>
    <div class="mt-2">
      <span class="tag tag-dot tag-available">已生效</span>
    </div>

    @if ($shareable)
        {{-- 已核验印章:合规上未取得有机认证前不标"有机",仅标认养关系已核验 --}}
        <div class="np-seal">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="#B33A26" stroke-width="1.6"/>
                <path d="M7 12.5l3 3 7-7.5" stroke="#B33A26" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            </svg>
            <span>认养关系已核验</span>
        </div>
    @endif
</div>