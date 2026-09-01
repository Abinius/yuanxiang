<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\TraceService;

/**
 * 2.5 溯源时间线（公开页，信任卖点，认养前转化入口）。
 *
 * 节点组装在 TraceService::nodesForPlot()，2.6 扫码页共用。
 * 路由-param 位置性：Tenant 在前、Plot 在后；因 SubstituteBindings 先于
 * TenantMiddleware，TenantScope 在绑定时未激活，保留显式 tenant_id 守卫。
 * 合规：cert_status=not_started，只写「有机肥（NXLB）投入品」，无认证宣称。
 */
class TraceController extends Controller
{
    public function __construct(private readonly TraceService $trace)
    {
    }

    public function show(Tenant $tenant, Plot $plot)
    {
        abort_if($plot->tenant_id !== $tenant->id, 404);

        return view('site.trace.show', [
            'tenant' => $tenant,
            'plot' => $plot,
            'nodes' => $this->trace->nodesForPlot($plot),
            'seo' => ['description' => $plot->code.' 溯源时间线 · 有机肥（NXLB）投入品，农事/检测/采收全程留痕'],
        ]);
    }
}
