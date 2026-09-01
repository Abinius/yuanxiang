<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TraceCode;
use App\Services\TraceService;

/**
 * 2.6 溯源码扫码页（公开，无 auth）：扫码看本箱地块全程 + 采收 + 肥料批次。
 *
 * TenantScoped 全局作用域 + TenantMiddleware 已设上下文 → `where('code', ...)`
 * 自动租户过滤，跨租户码自然 404（无需显式守卫）。
 * 每次扫码 scanned_count +1（MVP 不做会话去重）。
 * 路由-param 位置性：Tenant 在前、string $code 在后；切勿把 $code 类型提示为 TraceCode。
 * 合规：cert_status=not_started，只写「有机肥（NXLB）投入品 / 检测合格」。
 */
class ScanController extends Controller
{
    public function __construct(private readonly TraceService $trace)
    {
    }

    public function show(Tenant $tenant, string $code)
    {
        $traceCode = TraceCode::query()
            ->where('code', $code)
            ->with(['plot', 'harvest', 'adoption'])
            ->firstOrFail(); // 自动租户过滤 + 排除软删

        $plot = $traceCode->plot;
        abort_if(! $plot, 404, '该箱未绑定田块');

        $traceCode->incrementScans(); // 先于视图渲染，页面才显示新计数

        return view('site.scan.show', [
            'tenant' => $tenant,
            'traceCode' => $traceCode,
            'plot' => $plot,
            'harvest' => $traceCode->harvest,
            'nodes' => $this->trace->nodesForPlot($plot),
            'seo' => ['description' => '本箱溯源 · '.$plot->code.' · 有机肥（NXLB）投入品，每箱一码'],
        ]);
    }
}
