<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdoptionAdjustment;
use App\Support\Tenant;
use App\Services\AdjustmentService;
use Illuminate\Http\Request;

/**
 * 3.2 缺产补/退管理（tenant_admin）：按年度结算（保底规则引擎）→ 应用（部分退款）。
 * 路由-param 位置性：Tenant 在前、Adjustment 在后。
 */
class AdjustmentController extends Controller
{
    public function __construct(private readonly AdjustmentService $adjustments)
    {
    }

    public function index(Request $request)
    {
        $adjustments = AdoptionAdjustment::query()
            ->with(['adoption.user', 'adoption.adoptable', 'adoption.plan'])
            ->orderByDesc('id')
            ->get();

        return view('admin.adjustments.index', compact('adjustments'));
    }

    public function settle(Request $request)
    {
        $data = $request->validate([
            'season_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $created = $this->adjustments->runForSeason(Tenant::current(), (int) $data['season_year']);

        return redirect()->route('tenant.admin.adjustments.index', [])
            ->with('ok', '已生成 '.count($created).' 条补退');
    }

    public function apply(AdoptionAdjustment $adjustment, Request $request)
    {
        $this->adjustments->apply($adjustment);

        return back()->with('ok', '已应用');
    }

    /** A3 批量应用：按年度把所有 pending 补退一并 apply。 */
    public function applyAll(Request $request)
    {
        $data = $request->validate([
            'season_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $applied = $this->adjustments->applyAll(Tenant::current(), (int) $data['season_year']);

        return redirect()->route('tenant.admin.adjustments.index', [])
            ->with('ok', '已批量应用 '.$applied.' 条补退');
    }
}
