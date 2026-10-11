<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlotType;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\Plot;
use App\Services\AdoptionService;
use App\Services\SettingsService;
use App\Support\Tenant;
use App\Support\PlotRules;
use Illuminate\Http\Request;

/**
 * F1 田地动态管理（tenant_admin）：增/改/删 + 故事。
 *
 * - 田地不再硬编码（PlotSeeder 仅作测试种子）；生产田地由此 CRUD。
 * - 删除保护：存在已付款或在约/在途认养的田地禁止删除 → 409；改用下架(offline)。
 * - 路由-param 位置性：Tenant 在前、Plot 在后。
 * - 校验规则见 App\Support\PlotRules（与家人端共用一份）。
 */
class PlotController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AdoptionService $adoptions,
    ) {
    }

    public function index()
    {
        $plots = Plot::query()
            ->orderBy('order_index')
            ->orderBy('code')
            ->get();

        return view('admin.plots.index', compact('plots'));
    }

    public function create()
    {
        return view('admin.plots.form', $this->formData(new Plot()));
    }

    public function store(Request $request)
    {
        $plot = new Plot($request->validate(PlotRules::rules(Tenant::current(), $request)));
        $plot->tenant_id = Tenant::current()->id;
        $plot->attachCover($request->file('cover_image'));
        $plot->save();

        return redirect()->route('tenant.admin.plots.index', [])
            ->with('ok', '地块已添加');
    }

    public function edit(Plot $plot)
    {

        return view('admin.plots.form', $this->formData($plot));
    }

    public function update(Plot $plot, Request $request)
    {

        $plot->fill($request->validate(PlotRules::rules(Tenant::current(), $request, $plot)));
        $plot->attachCover($request->file('cover_image'));
        $plot->save();

        return redirect()->route('tenant.admin.plots.index', [])
            ->with('ok', '地块已更新');
    }

    public function destroy(Plot $plot)
    {
        $cancelled = $this->adoptions->deletePlot($plot);   // 不可删时抛 PlotNotDeletable → 409 + 原因

        return redirect()->route('tenant.admin.plots.index', [])
            ->with('ok', $this->adoptions->deletedMessage($plot->code, $cancelled));
    }

    public function updateStory(Plot $plot, Request $request)
    {

        $data = $request->validate([
            'story' => ['nullable', 'string', 'max:1000'],
        ]);

        $plot->update(['story' => $data['story'] ?? null]);   // nullable 字段空提交时会被 validate 移除

        return back()->with('ok', '地块故事已更新');
    }

    /** 新增/编辑共用的表单数据。 */
    private function formData(Plot $plot): array
    {
        return [
            'plot' => $plot,
            'farms' => Farm::orderBy('name')->get(),
            'plans' => Plan::orderBy('name')->get(),
            'groups' => Plot::where('type', PlotType::Group)->orderBy('code')->get(),
            'pricing' => $this->settings->pricing(),
        ];
    }
}
