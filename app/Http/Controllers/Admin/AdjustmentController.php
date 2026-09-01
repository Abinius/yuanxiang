<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionAdjustment;
use App\Models\Tenant;
use App\Services\AdjustmentService;
use Illuminate\Http\Request;

/**
 * 3.2 缺产补/退管理（tenant_admin）：按年度结算（保底规则引擎）→ 应用（部分退款）。
 * 路由-param 位置性：Tenant 在前、Adjustment 在后；显式 tenant_id 守卫。
 */
class AdjustmentController extends Controller
{
    public function __construct(private readonly AdjustmentService $adjustments)
    {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $adjustments = AdoptionAdjustment::query()
            ->with(['adoption.user', 'adoption.adoptable', 'adoption.plan'])
            ->orderByDesc('id')
            ->get();

        return view('admin.adjustments.index', compact('tenant', 'adjustments'));
    }

    public function settle(Tenant $tenant, Request $request)
    {
        $data = $request->validate([
            'season_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $created = $this->adjustments->runForSeason($tenant, (int) $data['season_year']);

        return redirect()->route('tenant.admin.adjustments.index', ['tenant' => $tenant->slug])
            ->with('ok', '已生成 '.count($created).' 条补退');
    }

    public function apply(Tenant $tenant, AdoptionAdjustment $adjustment, Request $request)
    {
        abort_if($adjustment->tenant_id !== $tenant->id, 404);
        $this->adjustments->apply($adjustment);

        return back()->with('ok', '已应用');
    }
}
