<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FarmLog;
use Illuminate\Http\Request;

/**
 * 农事内容后台管理（tenant_admin）：列表 + 软删。
 * 路由-param 位置性：Tenant 在前、FarmLog 在后；显式 tenant_id 守卫。
 */
class FarmLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = FarmLog::query()
            ->with(['plot', 'author'])
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.farm_logs.index', compact('logs'));
    }

    public function destroy(FarmLog $farmLog)
    {
        $farmLog->delete();

        return redirect()->route('tenant.admin.farm-logs.index', [])
            ->with('ok', '已删除');
    }
}
