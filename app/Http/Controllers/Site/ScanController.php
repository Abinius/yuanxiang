<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TraceCode;
use App\Services\TraceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 2.6 溯源码扫码页（公开，无 auth）：扫码看本箱地块全程 + 采收 + 肥料批次。
 *
 * TenantScoped 全局作用域 + TenantMiddleware 已设上下文 → `where('code', ...)`
 * 自动租户过滤，跨租户码自然 404（无需显式守卫）。
 * F6 R6.1：按 user+code（登录）或 IP+code（匿名）24h 去重计数，防同一箱刷屏。
 * F6 R6.2：扫码人即该箱认养人时显「你的箱」归属头。
 * 路由-param 位置性：Tenant 在前、string $code 在后；切勿把 $code 类型提示为 TraceCode。
 * 合规：cert_status=not_started，只写「有机肥（NXLB）投入品 / 检测合格」。
 */
class ScanController extends Controller
{
    public function __construct(private readonly TraceService $trace)
    {
    }

    public function show(Tenant $tenant, string $code, Request $request)
    {
        $traceCode = TraceCode::query()
            ->where('code', $code)
            ->with(['plot', 'harvest', 'adoption'])
            ->firstOrFail();

        $plot = $traceCode->plot;
        abort_if(! $plot, 404, '该箱未绑定田块');

        // F6 R6.1：24h 去重计数
        $key = $request->user()
            ? "scan:{$traceCode->code}:u{$request->user()->id}"
            : "scan:{$traceCode->code}:i{$request->ip()}";
        if (! Cache::has($key)) {
            $traceCode->incrementScans();
            Cache::put($key, 1, now()->addHours(24));
        }

        // F6 R6.2：扫码人即该箱认养人 → 你的箱
        $isMyBox = $request->user() && $traceCode->adoption_id
            && $request->user()->id === $traceCode->adoption->user_id;

        return view('site.scan.show', [
            'tenant' => $tenant,
            'traceCode' => $traceCode,
            'plot' => $plot,
            'harvest' => $traceCode->harvest,
            'nodes' => $this->trace->nodesForPlot($plot),
            'isMyBox' => $isMyBox,
            'seo' => ['description' => '本箱溯源 · '.$plot->code.' · 有机肥（NXLB）投入品，每箱一码'],
        ]);
    }
}