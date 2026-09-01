@extends('layouts.site')

@section('title', '礼盒定制')

@section('content')
  <div class="panel" style="max-width:560px;margin:0 auto">
    <div class="page-header">
      <h1 class="page-title">{{ $giftBox->festival->label() }} 礼盒 · 定制</h1>
      <a class="back-link" href="{{ route('tenant.my.gift.index', ['tenant' => $tenant->slug, 'adoption' => $adoption]) }}">我的礼盒 ›</a>
    </div>

    @if ($errors->any())
      <div class="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('tenant.my.gift.update', ['tenant' => $tenant->slug, 'adoption' => $adoption, 'giftBox' => $giftBox]) }}">
      @csrf
      <div class="field">
        <label>收礼人</label>
        <input class="input" name="recipient_name" required maxlength="60" value="{{ old('recipient_name', $giftBox->recipient_name) }}">
      </div>

      <div class="field">
        <label>收礼人手机</label>
        <input class="input" name="recipient_phone" required maxlength="20" value="{{ old('recipient_phone', $giftBox->recipient_phone) }}">
      </div>

      <div class="field">
        <label>收件地址(可选)</label>
        <select name="address_id" class="select">
          <option value="">(自定义地址可留空,后台补充)</option>
          @foreach ($addresses as $addr)
            <option value="{{ $addr->id }}" @selected(old('address_id', $giftBox->address_id) == $addr->id)>
              {{ $addr->name }} · {{ $addr->detail }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>寄语</label>
        <textarea class="textarea" name="message" rows="3" maxlength="500">{{ old('message', $giftBox->message) }}</textarea>
      </div>

      <div class="field">
        <label>亲笔签(手写签名,印到贺卡)</label>
        <div class="sign-canvas-wrap">
          <canvas id="sign" class="sign-canvas" width="300" height="120"></canvas>
          <button type="button" id="sign-clear" class="btn btn-ghost btn-sm">重签</button>
        </div>
      </div>
      <input type="hidden" name="signature" id="signature-input">

      <button class="btn btn-primary btn-block btn-lg" type="submit">保存定制</button>
    </form>

    <script>
      (function () {
        var canvas = document.getElementById('sign');
        var input = document.getElementById('signature-input');
        var ctx = canvas.getContext('2d');
        var drawing = false;
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#2B2620';
        function pos(e) {
          var r = canvas.getBoundingClientRect();
          return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) };
        }
        canvas.addEventListener('pointerdown', function (e) { drawing = true; ctx.beginPath(); ctx.moveTo(pos(e).x, pos(e).y); canvas.setPointerCapture(e.pointerId); });
        canvas.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
        canvas.addEventListener('pointerup', function () { drawing = false; input.value = canvas.toDataURL('image/png'); });
        document.getElementById('sign-clear').addEventListener('click', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); input.value = ''; });
      })();
    </script>
  </div>
@endsection