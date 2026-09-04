<?php

namespace App\Http\Controllers\Family;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\FarmLog;
use App\Models\FertilizerBatch;
use App\Models\GiftBox;
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
                ->first()?->permission_scope ?? [];
        }

        // 最近录入（只读，方便家人核对已提交内容）
        $recentLogs = FarmLog::query()
            ->with('plot')
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get();
        $recentBatches = FertilizerBatch::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $recentHarvests = Harvest::query()
            ->with('plot')
            ->orderByDesc('harvested_at')
            ->limit(5)
            ->get();

        // G4：今日待办 —— 当月物候农事 + 待制作礼盒 + 临期认养采收
        $todayMonth = (int) now()->format('n');
        $todos = [
            'stage' => config('goji.stages')[$todayMonth] ?? null,
            'giftDrafting' => GiftBox::query()
                ->whereIn('status', ['draft', 'making'])
                ->count(),
            'expiringAdoptions' => Adoption::query()
                ->where('status', 'active')
                ->whereBetween('end_date', [now(), now()->addDays(30)])
                ->count(),
        ];

        return view('family.dashboard', compact('tenant', 'user', 'scopes', 'recentLogs', 'recentBatches', 'recentHarvests', 'todos'));
    }
}
