<?php

namespace App\Http\Controllers\Family;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\Harvest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        if ($user->role === UserRole::TenantAdmin) {
            $scopes = ['farm_log', 'fertilizer', 'harvest'];
        } else {
            $scopes = $user->farmMemberships()
                ->where('tenant_id', $tenant->id)
                ->first()?->permission_scope ?? [];
        }

        // 最近录入（只读，方便家人核对已提交内容）
        $recentLogs = FarmLog::query()
            ->where('tenant_id', $tenant->id)
            ->with('plot')
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get();
        $recentBatches = FertilizerBatch::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $recentHarvests = Harvest::query()
            ->where('tenant_id', $tenant->id)
            ->with('plot')
            ->orderByDesc('harvested_at')
            ->limit(5)
            ->get();

        return view('family.dashboard', compact('tenant', 'user', 'scopes', 'recentLogs', 'recentBatches', 'recentHarvests'));
    }
}
