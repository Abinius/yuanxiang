<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftBox;
use App\Services\GiftBoxService;
use Illuminate\Http\Request;

/**
 * 3.3 礼盒后台（tenant_admin）：印制 → 发货（运单）→ 送达 + 贺卡打印。
 * 路由-param 位置性：Tenant 在前、GiftBox 在后；显式 tenant_id 守卫。
 */
class GiftBoxController extends Controller
{
    public function __construct(private readonly GiftBoxService $gifts)
    {
    }

    public function index(Request $request)
    {
        $giftBoxes = GiftBox::query()
            ->with(['adoption.user', 'adoption.adoptable', 'address'])
            ->orderByDesc('id')
            ->get();

        return view('admin.gift_boxes.index', compact('giftBoxes'));
    }

    public function making(GiftBox $giftBox, Request $request)
    {
        $this->gifts->markMaking($giftBox);

        return back()->with('ok', '已开始制作');
    }

    public function ship(GiftBox $giftBox, Request $request)
    {

        $data = $request->validate([
            'tracking_no' => ['required', 'string', 'max:80'],
            'carrier' => ['nullable', 'string', 'max:40'],
        ]);

        $this->gifts->markShipped($giftBox, $data['tracking_no'], $data['carrier'] ?? null);

        return back()->with('ok', '已发货');
    }

    public function delivered(GiftBox $giftBox, Request $request)
    {
        $this->gifts->markDelivered($giftBox);

        return back()->with('ok', '已送达');
    }

    public function print(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if (! $ids) {
            return redirect()->route('tenant.admin.gift-boxes.index', []);
        }

        $giftBoxes = GiftBox::query()
            ->whereIn('id', $ids)
            ->with(['adoption.user', 'address'])
            ->get();

        return view('admin.gift_boxes.print', compact('giftBoxes'));
    }
}
