<?php

namespace App\Http\Controllers\Family;

use App\Enums\PlotType;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use App\Support\PlotRules;
use Illuminate\Http\Request;

/**
 * F1.2 家人端田地录入（family/tenant_admin）。
 *
 * - scope=plot；family 按 farm_members.permission_scope 限权，tenant_admin 直通。
 * - farm_id 锁定为家人所属基地（不进表单）；tenant_admin 取本租户首个 farm。
 * - 仅建/改，不删（删除走 admin，防家人误删在约田地）。
 * - 校验规则见 App\Support\PlotRules（与后台共用一份）。
 */
class PlotController extends Controller
{
    public function create(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'plot');

        return view('family.plot.form', $this->formData($tenant, new Plot(), $member));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        $data = $request->validate(PlotRules::rules($tenant, $request, null, $member->farm_id));
        $data['farm_id'] = $member->farm_id;

        $plot = new Plot($data);
        $plot->tenant_id = $tenant->id;
        $plot->save();

        return redirect()->route('tenant.family.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已添加');
    }

    public function index(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        $plots = Plot::where('farm_id', $member->farm_id)
            ->orderBy('code')
            ->get();

        return view('family.plot.index', compact('tenant', 'plots'));
    }

    public function edit(Tenant $tenant, Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->tenant_id !== $tenant->id, 404);
        abort_if($plot->farm_id !== $member->farm_id, 403);

        return view('family.plot.form', $this->formData($tenant, $plot, $member));
    }

    public function update(Tenant $tenant, Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->tenant_id !== $tenant->id, 404);
        abort_if($plot->farm_id !== $member->farm_id, 403);

        // 家人不可改 farm_id（PlotRules 在传 farmId 时本就不收该字段）
        $plot->fill($request->validate(PlotRules::rules($tenant, $request, $plot, $member->farm_id)))->save();

        return redirect()->route('tenant.family.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已更新');
    }

    /** 新增/编辑共用的表单数据。 */
    private function formData(Tenant $tenant, Plot $plot, $member): array
    {
        return [
            'tenant' => $tenant,
            'plot' => $plot,
            'member' => $member,
            'plans' => Plan::orderBy('name')->get(),
            'groups' => Plot::where('type', PlotType::Group)->orderBy('code')->get(),
        ];
    }
}
