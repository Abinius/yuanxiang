<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlotType;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\SettingsService;
use App\Support\PlotRules;
use Illuminate\Http\Request;

/**
 * F1 田地动态管理（tenant_admin）：增/改/删 + 故事。
 *
 * - 田地不再硬编码（PlotSeeder 仅作测试种子）；生产田地由此 CRUD。
 * - 删除保护（F1.3）：存在在约/在途认养的田地禁止删除 → 409；改用下架(offline)。
 * - 路由-param 位置性：Tenant 在前、Plot 在后；显式 tenant_id 守卫。
 * - 校验规则见 App\Support\PlotRules（与家人端共用一份）。
 */
class PlotController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(Tenant $tenant)
    {
        $plots = Plot::query()
            ->orderBy('order_index')
            ->orderBy('code')
            ->get();

        return view('admin.plots.index', compact('tenant', 'plots'));
    }

    public function create(Tenant $tenant)
    {
        return view('admin.plots.form', $this->formData($tenant, new Plot()));
    }

    public function store(Tenant $tenant, Request $request)
    {
        $plot = new Plot($request->validate(PlotRules::rules($tenant, $request)));
        $plot->tenant_id = $tenant->id;
        $plot->save();

        return redirect()->route('tenant.admin.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已添加');
    }

    public function edit(Tenant $tenant, Plot $plot)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        return view('admin.plots.form', $this->formData($tenant, $plot));
    }

    public function update(Tenant $tenant, Plot $plot, Request $request)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        $plot->fill($request->validate(PlotRules::rules($tenant, $request, $plot)))->save();

        return redirect()->route('tenant.admin.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已更新');
    }

    public function destroy(Tenant $tenant, Plot $plot)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        // F1.3 删除保护：在约/在途认养存在则禁止删除
        if ($plot->hasInFlightAdoptions()) {
            return redirect()
                ->route('tenant.admin.plots.index', ['tenant' => $tenant->slug])
                ->with('error', '该地块有在约认养，无法删除；可改用「下架」停止新认养。');
        }

        $plot->delete();

        return redirect()->route('tenant.admin.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已删除');
    }

    public function updateStory(Tenant $tenant, Plot $plot, Request $request)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        $data = $request->validate([
            'story' => ['nullable', 'string', 'max:1000'],
        ]);

        $plot->update(['story' => $data['story']]);

        return back()->with('ok', '地块故事已更新');
    }

    /** 新增/编辑共用的表单数据。 */
    private function formData(Tenant $tenant, Plot $plot): array
    {
        return [
            'tenant' => $tenant,
            'plot' => $plot,
            'farms' => Farm::orderBy('name')->get(),
            'plans' => Plan::orderBy('name')->get(),
            'groups' => Plot::where('type', PlotType::Group)->orderBy('code')->get(),
            'pricing' => $this->settings->pricing($tenant),
        ];
    }
}
