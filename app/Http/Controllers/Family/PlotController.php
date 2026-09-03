<?php

namespace App\Http\Controllers\Family;

use App\Enums\PlotType;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * F1.2 家人端田地录入（family/tenant_admin）。
 *
 * - scope=plot；family 按 farm_members.permission_scope 限权，tenant_admin 直通。
 * - farm_id 锁定为家人所属基地（表单不可改）；tenant_admin 取本租户首个 farm。
 * - 仅建/改，不删（删除走 admin，防家人误删在约田地）。
 */
class PlotController extends Controller
{
    public function create(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'plot');

        return view('family.plot.form', [
            'tenant' => $tenant,
            'plot' => new Plot(),
            'member' => $member,
            'plans' => Plan::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'groups' => Plot::where('tenant_id', $tenant->id)->where('type', PlotType::Group)->orderBy('code')->get(),
        ]);
    }

    public function store(Tenant $tenant, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        $data = $this->validateData($request, $tenant, $member->farm_id);
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
        $plots = Plot::where('tenant_id', $tenant->id)
            ->where('farm_id', $member->farm_id)
            ->orderBy('code')
            ->get();

        return view('family.plot.index', compact('tenant', 'plots'));
    }

    public function edit(Tenant $tenant, Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->tenant_id !== $tenant->id, 404);
        abort_if($plot->farm_id !== $member->farm_id, 403);

        return view('family.plot.form', [
            'tenant' => $tenant,
            'plot' => $plot,
            'member' => $member,
            'plans' => Plan::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'groups' => Plot::where('tenant_id', $tenant->id)->where('type', PlotType::Group)->orderBy('code')->get(),
        ]);
    }

    public function update(Tenant $tenant, Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->tenant_id !== $tenant->id, 404);
        abort_if($plot->farm_id !== $member->farm_id, 403);

        $data = $this->validateData($request, $tenant, $member->farm_id, $plot);
        // 家人不可改 farm_id（锁定本基地）
        unset($data['farm_id']);
        $plot->fill($data)->save();

        return redirect()->route('tenant.family.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已更新');
    }

    private function validateData(Request $request, Tenant $tenant, int $farmId, ?Plot $plot = null): array
    {
        $type = $request->input('type');

        return $request->validate([
            'plan_id' => ['nullable', Rule::exists('plans', 'id')->where('tenant_id', $tenant->id)],
            'parent_plot_id' => [
                'nullable',
                Rule::exists('plots', 'id')->where('tenant_id', $tenant->id)->where('type', PlotType::Group->value),
                Rule::requiredIf($type === PlotType::Plant->value),
            ],
            'type' => ['required', Rule::enum(PlotType::class)],
            'code' => ['required', 'string', 'max:40', Rule::unique('plots', 'code')
                ->where('tenant_id', $tenant->id)->ignore($plot?->id)],
            'mu_area' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'price_yearly' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['available', 'adopted', 'sold_out', 'offline'])],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'story' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
