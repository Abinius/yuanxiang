{{-- R3 竖版地块卡：封面在上、文字区在下；无封面时品牌色占位 + 编号水印，不请求任何图片资源。 --}}
<a class="product-card card-portrait" href="{{ route('tenant.adopt.show', ['plot' => $plot]) }}">
  @if ($plot->coverUrl())
    <div class="card-cover">
      <img src="{{ $plot->coverUrl() }}" alt="{{ $plot->code }} 封面" loading="lazy">
    </div>
  @else
    <div class="card-cover card-cover--placeholder">{{ $plot->code }}</div>
  @endif
  <div class="card-body">
    <div class="code">{{ $plot->code }}</div>
    <div class="price">{{ $price }}</div>
    <div class="meta">{{ $meta }}</div>
    <div class="mt-2">
      <span class="tag {{ $plot->status->value === 'available' ? 'tag-available' : ($plot->status->value === 'adopted' ? 'tag-adopted' : 'tag-off') }}">
        {{ $plot->status->label() }}
      </span>
    </div>
  </div>
</a>
