<?php

namespace App\Http\Controllers\Family;

use App\Jobs\SendHarvestNoticeJob;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 家人端：录入采收（harvests）。
 * scope=harvest；handler_id=当前 user；season_year 默认本年。
 */
class HarvestController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveries,
    ) {}

    public function create(Tenant $tenant, Request $request)
    {
        $this->assertScope($request, 'harvest');

        // G2：常用地块置顶（该家人最近 3 次采收的 plot_id，其余按 code）
        $recentIds = Harvest::query()
            ->where('tenant_id', $tenant->id)
            ->where('handler_id', $request->user()->id)
            ->whereNotNull('plot_id')
            ->orderByDesc('harvested_at')
            ->limit(3)
            ->pluck('plot_id')
            ->unique()
            ->values();
        $recentOrder = $recentIds->flip(); // plot_id → 0(最近),1,2
        $plots = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')
            ->orderBy('code')
            ->get()
            ->sortBy(fn ($p) => $recentOrder->get($p->id, PHP_INT_MAX))
            ->values();

        return view('family.harvest.create', compact('tenant', 'plots'));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'harvest');

        $data = $request->validate([
            'plot_id' => ['required', Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)],
            'season_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'harvested_at' => ['required', 'date'],
            'dry_weight_kg' => ['required', 'numeric', 'min:0'],
            'quality_grade' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $harvest = new Harvest();
        $harvest->tenant_id = $tenant->id;
        $harvest->farm_id = $member->farm_id;
        $harvest->plot_id = $data['plot_id'];
        $harvest->season_year = $data['season_year'] ?? now()->year;
        $harvest->harvested_at = $data['harvested_at'];
        $harvest->dry_weight_kg = $data['dry_weight_kg'];
        $harvest->quality_grade = $data['quality_grade'] ?? null;
        $harvest->handler_id = $request->user()->id;
        $harvest->notes = $data['notes'] ?? null;
        $harvest->save();

        // 3.1：采收通知该田块认养人「你的田今天采了」（queue 消费，mock 只落库）
        SendHarvestNoticeJob::dispatch($harvest->id);

        // G5/A4：采收完成一键联动生码+配送草稿（每箱一码），admin 发货台见草稿
        $this->deliveries->createForHarvest($harvest);

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', '采收已记录，配送草稿与溯源码已生成');
    }

    /** G8：编辑（复用 create 视图）。仅 handler 本人或 tenant_admin 可改。 */
    public function edit(Tenant $tenant, Harvest $harvest, Request $request)
    {
        $this->assertScope($request, 'harvest');
        abort_if($harvest->tenant_id !== $tenant->id, 404);
        abort_if($harvest->handler_id !== $request->user()->id && $request->user()->role->value !== 'tenant_admin', 404);

        $plots = Plot::where('tenant_id', $tenant->id)->where('type', 'plot')->orderBy('code')->get();

        return view('family.harvest.create', compact('tenant', 'plots', 'harvest'));
    }

    public function update(Tenant $tenant, Harvest $harvest, Request $request)
    {
        $member = $this->assertScope($request, 'harvest');
        abort_if($harvest->tenant_id !== $tenant->id, 404);
        abort_if($harvest->handler_id !== $request->user()->id && $request->user()->role->value !== 'tenant_admin', 404);

        $data = $request->validate([
            'plot_id' => ['required', Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)],
            'season_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'harvested_at' => ['required', 'date'],
            'dry_weight_kg' => ['required', 'numeric', 'min:0'],
            'quality_grade' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $harvest->update([
            'plot_id' => $data['plot_id'],
            'season_year' => $data['season_year'] ?? $harvest->season_year,
            'harvested_at' => $data['harvested_at'],
            'dry_weight_kg' => $data['dry_weight_kg'],
            'quality_grade' => $data['quality_grade'],
            'notes' => $data['notes'],
        ]);

        return redirect()->route('tenant.family.dashboard', ['tenant' => $tenant->slug])
            ->with('ok', '已更新');
    }
}
