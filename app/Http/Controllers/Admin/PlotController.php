<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * F1 地块故事：admin 维护每块地的种植故事文案，认养详情页展示。
 * 路由-param 位置性：Tenant 在前、Plot 在后；显式 tenant_id 守卫。
 */
class PlotController extends Controller
{
    public function index(Tenant $tenant, Request $request)
    {
        $plots = Plot::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('code')
            ->get();

        return view('admin.plots.index', compact('tenant', 'plots'));
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
}