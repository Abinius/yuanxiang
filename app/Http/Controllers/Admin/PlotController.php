<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PlotType;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * F1 田地动态管理（tenant_admin）：增/改/删 + 故事。
 *
 * - 田地不再硬编码（PlotSeeder 仅作测试种子）；生产田地由此 CRUD。
 * - 删除保护（F1.3）：存在在约/在途认养的田地禁止删除 → 409；改用下架(offline)。
 * - 路由-param 位置性：Tenant 在前、Plot 在后；显式 tenant_id 守卫。
 */
class PlotController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $plots = Plot::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('order_index')
            ->orderBy('code')
            ->get();

        return view('admin.plots.index', compact('tenant', 'plots'));
    }

    public function create(Tenant $tenant, Request $request)
    {
        return view('admin.plots.form', [
            'tenant' => $tenant,
            'plot' => new Plot(),
            'farms' => Farm::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'plans' => Plan::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'groups' => Plot::where('tenant_id', $tenant->id)->where('type', PlotType::Group)->orderBy('code')->get(),
            'pricing' => $this->settings->pricing($tenant),
        ]);
    }

    public function store(Tenant $tenant, Request $request)
    {
        $data = $this->validateData($request, $tenant);
        $plot = new Plot($data);
        $plot->tenant_id = $tenant->id;
        $plot->save();

        return redirect()->route('tenant.admin.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已添加');
    }

    public function edit(Tenant $tenant, Plot $plot, Request $request)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        return view('admin.plots.form', [
            'tenant' => $tenant,
            'plot' => $plot,
            'farms' => Farm::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'plans' => Plan::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'groups' => Plot::where('tenant_id', $tenant->id)->where('type', PlotType::Group)->orderBy('code')->get(),
            'pricing' => $this->settings->pricing($tenant),
        ]);
    }

    public function update(Tenant $tenant, Plot $plot, Request $request)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);
        $data = $this->validateData($request, $tenant, $plot);
        $plot->fill($data)->save();

        return redirect()->route('tenant.admin.plots.index', ['tenant' => $tenant->slug])
            ->with('ok', '地块已更新');
    }

    public function destroy(Tenant $tenant, Plot $plot, Request $request)
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

    private function validateData(Request $request, Tenant $tenant, ?Plot $plot = null): array
    {
        $type = $request->input('type');

        return $request->validate([
            'farm_id' => ['required', Rule::exists('farms', 'id')->where('tenant_id', $tenant->id)],
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
