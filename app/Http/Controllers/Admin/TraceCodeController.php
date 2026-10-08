<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Harvest;
use App\Support\Tenant;
use App\Models\TraceCode;
use App\Services\TraceCodeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 后台溯源码管理（tenant_admin）：生成（按采收批量「每箱一码」）+ 打印标签。
 * 路由-param 位置性：Tenant 在前；store 无模型绑定，校验 Rule::exists 按租户过滤。
 */
class TraceCodeController extends Controller
{
    public function __construct(private readonly TraceCodeService $codes)
    {
    }

    public function index(Request $request)
    {
        $traceCodes = TraceCode::query()
            ->with(['plot', 'harvest'])
            ->orderByDesc('id')
            ->get();

        return view('admin.trace_codes.index', compact('traceCodes'));
    }

    public function create(Request $request)
    {
        $harvests = Harvest::query()
            ->with('plot')
            ->orderByDesc('harvested_at')
            ->get();

        return view('admin.trace_codes.form', compact('harvests'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'harvest_id' => ['required', Rule::exists('harvests', 'id')->where('tenant_id', Tenant::current()->id)],
            'count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $harvest = Harvest::findOrFail($data['harvest_id']); // TenantScoped，上下文内自动过滤
        $generated = $this->codes->generate($harvest, (int) $data['count']);

        return redirect()->route('tenant.admin.trace-codes.print', [
            'ids' => implode(',', array_map(fn ($c) => $c->id, $generated)),
        ]);
    }

    public function print(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if (! $ids) {
            return redirect()->route('tenant.admin.trace-codes.index', []);
        }

        $traceCodes = TraceCode::query()
            ->whereIn('id', $ids)
            ->with('plot')
            ->get();

        // 双保险：上下文过滤已保证 tenant 一致，仍逐条校验防意外

        return view('admin.trace_codes.print', compact('traceCodes'));
    }
}
