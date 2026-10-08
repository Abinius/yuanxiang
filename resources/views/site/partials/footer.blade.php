<footer class="site-footer">
@php
  $footer = app(\App\Services\SettingsService::class)->footer();
@endphp
{{ $footer['copyright'] }} · {{ $tenant->name }}
@if ($footer['icp'])
  · <a class="mono" href="https://beian.miit.gov.cn/" target="_blank" rel="noreferrer">{{ $footer['icp'] }}</a>
@endif
@if ($footer['contact'])
  · 客服：{{ $footer['contact'] }}
@endif
<br>
<a href="{{ route('tenant.agreement.show', ['key' => 'service']) }}">服务协议</a>
· <a href="{{ route('tenant.agreement.show', ['key' => 'privacy']) }}">隐私政策</a>
</footer>
