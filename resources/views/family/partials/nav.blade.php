<a href="{{ route('tenant.family.dashboard', []) }}">家人端</a>
@if (auth()->user()->role->value === 'tenant_admin')
  <a href="{{ route('tenant.admin.dashboard', []) }}">管理后台</a>
@endif
<a href="{{ route('tenant.home', []) }}">前台</a>
<span class="user">{{ auth()->user()->nickname }}</span>
<form method="POST" action="{{ route('tenant.logout', []) }}" class="inline">
  @csrf
  <button type="submit">退出</button>
</form>
