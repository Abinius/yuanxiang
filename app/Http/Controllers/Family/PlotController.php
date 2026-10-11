<?php

namespace App\Http\Controllers\Family;

use App\Enums\PlotType;
use App\Exceptions\PlotNotDeletable;
use App\Models\Plan;
use App\Models\Plot;
use App\Services\AdoptionService;
use App\Support\Tenant;
use App\Support\PlotRules;
use Illuminate\Http\Request;

/**
 * F1.2 家人端田地录入（family/tenant_admin）。
 *
 * - scope=plot；family 按 farm_members.permission_scope 限权，tenant_admin 直通。
 * - farm_id 锁定为家人所属基地（不进表单）；tenant_admin 取本租户首个 farm。
 * - 删除与后台共用同一份可删性判定；已付款/在约地块对家人是权限边界，转 403。
 * - 校验规则见 App\Support\PlotRules（与后台共用一份）。
 */
class PlotController extends Controller
{
    /** 家人不能改的字段：定价与上下架是经营决策，归商户后台。 */
    private const FAMILY_READONLY = ['price_yearly', 'status'];

    public function __construct(private readonly AdoptionService $adoptions)
    {
    }

    public function create(Request $request)
    {
        $member = $this->assertScope($request, 'plot');

        return view('family.plot.form', $this->formData(new Plot(), $member));
    }

    public function store(Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        $data = $request->validate(PlotRules::rules(Tenant::current(), $request, null, $member->farm_id, self::FAMILY_READONLY));
        $data['farm_id'] = $member->farm_id;
        $data['status'] = 'available';

        $plot = new Plot($data);
        $plot->tenant_id = Tenant::current()->id;
        $plot->attachCover($request->file('cover_image'));
        $plot->save();

        return redirect()->route('tenant.family.plots.index', [])
            ->with('ok', '地块已添加');
    }

    public function index(Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        $plots = Plot::where('farm_id', $member->farm_id)
            ->orderBy('code')
            ->get();

        return view('family.plot.index', compact('plots'));
    }

    public function edit(Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->farm_id !== $member->farm_id, 403);

        return view('family.plot.form', $this->formData($plot, $member));
    }

    public function update(Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->farm_id !== $member->farm_id, 403);

        // 家人不可改 farm_id（PlotRules 在传 farmId 时本就不收该字段）与定价/上下架
        $plot->fill($request->validate(PlotRules::rules(Tenant::current(), $request, $plot, $member->farm_id, self::FAMILY_READONLY)));
        $plot->attachCover($request->file('cover_image'));
        $plot->save();

        return redirect()->route('tenant.family.plots.index', [])
            ->with('ok', '地块已更新');
    }

    public function destroy(Plot $plot, Request $request)
    {
        $member = $this->assertScope($request, 'plot');
        abort_if($plot->farm_id !== $member->farm_id, 403);

        // 已付款/在约地块对家人是权限边界：同一份可删性判定，这里按家人口径转 403。
        try {
            $cancelled = $this->adoptions->deletePlot($plot);
        } catch (PlotNotDeletable) {
            abort(403, '该地块已有认养记录，无删除权限。');
        }

        return redirect()->route('tenant.family.plots.index', [])
            ->with('ok', $this->adoptions->deletedMessage($plot->code, $cancelled));
    }

    /** 新增/编辑共用的表单数据。 */
    private function formData(Plot $plot, $member): array
    {
        return [
            'plot' => $plot,
            'member' => $member,
            'plans' => Plan::orderBy('name')->get(),
            'groups' => Plot::where('type', PlotType::Group)->orderBy('code')->get(),
        ];
    }
}
