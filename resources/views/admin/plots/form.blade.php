@extends('layouts.dashboard')

@section('title', $plot->exists ? '编辑地块' : '添加地块')

@section('nav_right')
  <a href="{{ route('tenant.admin.plots.index', ['tenant' => $tenant->slug]) }}">地块列表</a>
  <a href="{{ route('tenant.home', ['tenant' => $tenant->slug]) }}">前台</a>
  <span class="user">{{ auth()->user()->nickname }}</span>
@endsection

@section('content')
  <h1 class="hero-title mb-4" style="font-size:var(--ds-h2)">{{ $plot->exists ? '编辑地块' : '添加地块' }}</h1>

  @if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $plot->exists ? route('tenant.admin.plots.update', ['tenant' => $tenant->slug, 'plot' => $plot]) : route('tenant.admin.plots.store', ['tenant' => $tenant->slug]) }}">
    @csrf
    @if ($plot->exists)
      @method('PUT')
    @endif

    <div class="card-grid grid-2 mb-4">
      <div class="field">
        <label>类型</label>
        <select name="type" class="select" onchange="toggleParent(this)">
          @foreach (\App\Enums\PlotType::cases() as $t)
            <option value="{{ $t->value }}" @selected(old('type', $plot->type?->value) === $t->value)>{{ $t->value === 'plot' ? '分地档' : ($t->value === 'group' ? '拼团田' : '单株') }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>地块编号</label>
        <input class="input" name="code" required maxlength="40" value="{{ old('code', $plot->code ?? '') }}" placeholder="FD-A01 / PT-01 / Z-01-015">
        <div class="field-hint">租户内唯一。</div>
      </div>
    </div>

    <div class="card-grid grid-2 mb-4">
      <div class="field">
        <label>所属基地</label>
        <select name="farm_id" class="select" required>
          @foreach ($farms as $farm)
            <option value="{{ $farm->id }}" @selected(old('farm_id', $plot->farm_id) == $farm->id)>{{ $farm->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>认养方案(可选)</label>
        <select name="plan_id" class="select">
          <option value="">— 不绑定 —</option>
          @foreach ($plans as $plan)
            <option value="{{ $plan->id }}" @selected(old('plan_id', $plot->plan_id) == $plan->id)>{{ $plan->name }}</option>
          @endforeach
        </select>
        <div class="field-hint">保底/主档位在方案；下方年费为快照覆盖。</div>
      </div>
    </div>

    <div class="card-grid grid-2 mb-4">
      <div class="field">
        <label>面积(亩)</label>
        <input class="input" type="number" step="0.01" name="mu_area" min="0" value="{{ old('mu_area', $plot->mu_area ?? '') }}">
      </div>
      <div class="field">
        <label>年费快照(元,可选)</label>
        <input class="input" type="number" name="price_yearly" min="0" value="{{ old('price_yearly', $plot->price_yearly ?? '') }}">
        <div class="field-hint">留空则取方案价。</div>
      </div>
    </div>

    <div class="field" id="parent-field" style="{{ (old('type', $plot->type?->value) === 'plant') ? '' : 'display:none' }}">
      <label>所属拼团田(单株必填)</label>
      <select name="parent_plot_id" class="select">
        <option value="">— 请选择 —</option>
        @foreach ($groups as $g)
          <option value="{{ $g->id }}" @selected(old('parent_plot_id', $plot->parent_plot_id) == $g->id)>{{ $g->code }}</option>
        @endforeach
      </select>
    </div>

    <div class="card-grid grid-2 mb-4">
      <div class="field">
        <label>状态</label>
        <select name="status" class="select">
          @foreach (['available' => '可认养', 'adopted' => '已认养', 'sold_out' => '已售罄', 'offline' => '下架'] as $v => $lbl)
            <option value="{{ $v }}" @selected(old('status', $plot->status?->value ?? 'available') === $v)>{{ $lbl }}</option>
          @endforeach
        </select>
        <div class="field-hint">下架停止新认养，保留在约权益。</div>
      </div>
      <div class="field">
        <label>排序</label>
        <input class="input" type="number" name="order_index" min="0" value="{{ old('order_index', $plot->order_index ?? 0) }}">
      </div>
    </div>

    <div class="field mb-4">
      <label>地块故事(可选)</label>
      <textarea class="textarea" name="story" rows="3" maxlength="1000" placeholder="例:这块田挨着涝坝,晨露重,夏果格外甜。">{{ old('story', $plot->story ?? '') }}</textarea>
    </div>

    <button class="btn btn-primary btn-block btn-lg" type="submit">{{ $plot->exists ? '保存修改' : '添加地块' }}</button>
  </form>

  @if ($plot->exists && $plot->hasInFlightAdoptions())
    <div class="alert mt-4">该地块有在约认养，无法删除——可改用「下架」。</div>
  @elseif ($plot->exists)
    <form method="POST" action="{{ route('tenant.admin.plots.destroy', ['tenant' => $tenant->slug, 'plot' => $plot]) }}" onsubmit="return confirm('确认删除地块 {{ $plot->code }}？')" class="mt-4">
      @csrf
      @method('DELETE')
      <button class="btn btn-ghost btn-block" type="submit">删除地块</button>
    </form>
  @endif

  <script>
    function toggleParent(sel) {
      document.getElementById('parent-field').style.display = sel.value === 'plant' ? '' : 'none';
    }
  </script>
@endsection
