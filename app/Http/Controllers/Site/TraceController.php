<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Services\TraceService;

/**
 * 2.5 溯源时间线（公开页，信任卖点，认养前转化入口）。
 *
 * 节点组装在 TraceService::nodesForPlot()，2.6 扫码页共用。
 * 合规：cert_status=not_started，只写「有机肥（NXLB）投入品」，无认证宣称。
 */
class TraceController extends Controller
{
    public function __construct(private readonly TraceService $trace)
    {
    }

    public function show(Plot $plot)
    {

        return view('site.trace.show', [
            'plot' => $plot,
            'nodes' => $this->trace->nodesForPlot($plot),
            'seo' => ['description' => $plot->code.' 溯源时间线 · 有机肥（NXLB）投入品，农事/检测/采收全程留痕'],
        ]);
    }
}
