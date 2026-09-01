<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Harvest;
use App\Models\Tenant;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 3.1 配送管理（tenant_admin）：按采收打单 → 发货（录运单）→ 打印打单。
 * 签收由认养人在 C 端「我的田」确认（MyPlotController::receive）。
 * 路由-param 位置性：Tenant 在前、Delivery 在后；显式 tenant_id 守卫。
 */
class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries)
    {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $deliveries = Delivery::query()
            ->with(['adoption.user', 'harvest.plot', 'address'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.deliveries.index', compact('tenant', 'deliveries'));
    }

    public function create(Tenant $tenant, Request $request)
    {
        $harvests = Harvest::query()
            ->where('tenant_id', $tenant->id)
            ->with('plot')
            ->orderByDesc('harvested_at')
            ->get();

        return view('admin.deliveries.create', compact('tenant', 'harvests'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            'harvest_id' => ['required', Rule::exists('harvests', 'id')->where('tenant_id', $tenant->id)],
        ]);

        $harvest = Harvest::findOrFail($data['harvest_id']);
        $created = $this->deliveries->createForHarvest($harvest);

        return redirect()->route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug])
            ->with('ok', '已生成 '.count($created).' 单配送');
    }

    public function ship(Tenant $tenant, Delivery $delivery, Request $request)
    {
        abort_if($delivery->tenant_id !== $tenant->id, 404);
        abort_unless($delivery->status === DeliveryStatus::Pending, 422, '仅待发货可发运');

        $data = $request->validate([
            'tracking_no' => ['required', 'string', 'max:80'],
            'carrier' => ['nullable', 'string', 'max:40'],
        ]);

        $this->deliveries->markShipped($delivery, $data['tracking_no'], $data['carrier'] ?? null);

        return back()->with('ok', '已发货');
    }

    public function print(Tenant $tenant, Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if (! $ids) {
            return redirect()->route('tenant.admin.deliveries.index', ['tenant' => $tenant->slug]);
        }

        $deliveries = Delivery::query()
            ->whereIn('id', $ids)
            ->with(['adoption.user', 'address', 'harvest.plot'])
            ->get();

        abort_if($deliveries->contains(fn ($d) => $d->tenant_id !== $tenant->id), 404);

        return view('admin.deliveries.print', compact('tenant', 'deliveries'));
    }
}
