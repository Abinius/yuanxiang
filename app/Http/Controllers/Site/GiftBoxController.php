<?php

namespace App\Http\Controllers\Site;

use App\Enums\GiftFestival;
use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\GiftBox;
use App\Services\GiftBoxService;
use Illuminate\Http\Request;

/**
 * 3.3 节日礼盒（云乡民）：我的田 → 按权益额度定制礼盒（收礼人/寄语/亲笔签）。
 * owner-gated（照 MyPlotController）。路由-param 位置性：Tenant 在前。
 */
class GiftBoxController extends Controller
{
    public function __construct(private readonly GiftBoxService $gifts)
    {
    }

    public function index(Adoption $adoption, Request $request)
    {
        abort_if($adoption->user_id !== $request->user()->id, 404);

        $giftBoxes = $adoption->giftBoxes()->orderByDesc('id')->get();
        $quota = $adoption->plan?->festival_quota ?? [];

        return view('site.gift.index', compact('adoption', 'giftBoxes', 'quota'));
    }

    public function create(Adoption $adoption, Request $request)
    {
        abort_if($adoption->user_id !== $request->user()->id, 404);

        $quota = $adoption->plan?->festival_quota ?? [];
        $year = (int) now()->format('Y');
        $festivals = collect(GiftFestival::cases())
            ->map(fn ($f) => [
                'value' => $f->value,
                'label' => $f->label(),
                'remaining' => max(0, (int) data_get($quota, $f->value, 0)
                    - $adoption->giftBoxes()->where('festival', $f->value)->where('year', $year)->count()),
            ])
            ->filter(fn ($f) => $f['remaining'] > 0)
            ->values();

        return view('site.gift.create', compact('adoption', 'festivals'));
    }

    public function store(Adoption $adoption, Request $request)
    {
        abort_if($adoption->user_id !== $request->user()->id, 404);

        $data = $request->validate([
            'festival' => ['required', 'in:spring,dragon_boat,mid_autumn'],
        ]);

        $giftBox = $this->gifts->create($adoption, $data['festival'], (int) now()->format('Y'));

        return redirect()->route('tenant.my.gift.customize', ['adoption' => $adoption, 'giftBox' => $giftBox]);
    }

    public function customize(Adoption $adoption, GiftBox $giftBox, Request $request)
    {
        abort_if($adoption->user_id !== $request->user()->id, 404);
        abort_if($giftBox->adoption_id !== $adoption->id, 404);

        $addresses = $request->user()->addresses()->get();

        return view('site.gift.customize', compact('adoption', 'giftBox', 'addresses'));
    }

    public function updateCustomize(Adoption $adoption, GiftBox $giftBox, Request $request)
    {
        abort_if($adoption->user_id !== $request->user()->id, 404);
        abort_if($giftBox->adoption_id !== $adoption->id, 404);

        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:60'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'address_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:500'],
            'signature' => ['nullable', 'string'],
        ]);

        $this->gifts->customize($giftBox, $data, $data['signature'] ?? null);

        return redirect()->route('tenant.my.gift.index', ['adoption' => $adoption])
            ->with('ok', '礼盒已定制');
    }
}
