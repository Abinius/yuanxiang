<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\GiftBoxStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\GiftBox;
use App\Models\Tenant;
use App\Services\DeliveryService;
use App\Services\GiftBoxService;
use Illuminate\Http\Request;

/**
 * A2 统一发货工作台：聚合两套出库队列（deliveries.pending + gift_boxes.draft/making），
 * 一个页面统一录运单/发货。签收仍走原链路（C 端确认 / 礼盒送达）。
 */
class ShipmentWorkbenchController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly GiftBoxService $gifts,
    ) {
    }

    /** 统一出库队列：待发配送（按采收倒序）+ 待发礼盒（草稿/制作中）。 */
    public function index(Tenant $tenant, Request $request)
    {
        $pendingDeliveries = Delivery::query()
            ->where('status', DeliveryStatus::Pending->value)
            ->with(['adoption.user', 'harvest.plot', 'address'])
            ->orderByDesc('id')
            ->get();

        $pendingGifts = GiftBox::query()
            ->whereIn('status', [GiftBoxStatus::Draft->value, GiftBoxStatus::Making->value])
            ->with(['adoption.user', 'adoption.adoptable', 'address'])
            ->orderByDesc('id')
            ->get();

        return view('admin.shipments.index', compact('tenant', 'pendingDeliveries', 'pendingGifts'));
    }

    /** 统一发货：配送单录运单发货。 */
    public function shipDelivery(Tenant $tenant, Delivery $delivery, Request $request)
    {
        abort_if($delivery->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'tracking_no' => ['required', 'string', 'max:80'],
            'carrier' => ['nullable', 'string', 'max:40'],
        ]);

        $this->deliveries->markShipped($delivery, $data['tracking_no'], $data['carrier'] ?? null);

        return back()->with('ok', '配送已发货');
    }

    /** 统一发货：礼盒录运单发货（draft/making 均可直接发）。 */
    public function shipGift(Tenant $tenant, GiftBox $giftBox, Request $request)
    {
        abort_if($giftBox->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'tracking_no' => ['required', 'string', 'max:80'],
            'carrier' => ['nullable', 'string', 'max:40'],
        ]);

        $this->gifts->markShipped($giftBox, $data['tracking_no'], $data['carrier'] ?? null);

        return back()->with('ok', '礼盒已发货');
    }

    /** 礼盒标记开始制作（draft → making）。 */
    public function makeGift(Tenant $tenant, GiftBox $giftBox)
    {
        abort_if($giftBox->tenant_id !== $tenant->id, 404);
        $this->gifts->markMaking($giftBox);

        return back()->with('ok', '已开始制作');
    }
}