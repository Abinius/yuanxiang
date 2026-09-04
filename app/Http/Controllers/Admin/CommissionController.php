<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLedger;
use App\Models\Payout;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * M4 佣金/提现审核（tenant_admin）。
 * - ledger：本租户佣金流水汇总+明细。
 * - payouts：type=commission 提现申请待审列表；approve → paid，reject → failed 并回流佣金。
 */
class CommissionController extends Controller
{
    public function ledger(Tenant $tenant, Request $request)
    {
        $base = CommissionLedger::query();

        return view('admin.commissions.index', [
            'tenant' => $tenant,
            'summary' => [
                'pending' => (float) (clone $base)->where('status', 'pending')->sum('amount'),
                'available' => (float) (clone $base)->where('status', 'available')->sum('amount'),
                'settled' => (float) (clone $base)->where('status', 'settled')->sum('amount'),
                'frozen' => (float) (clone $base)->where('status', 'frozen')->sum('amount'),
            ],
            'items' => $base->with(['user', 'adoption.adoptable'])->orderByDesc('created_at')->limit(100)->get(),
            'payouts' => Payout::query()
                ->where('type', 'commission')
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function approve(Tenant $tenant, Payout $payout, Request $request)
    {
        abort_if($payout->tenant_id !== $tenant->id || $payout->type !== 'commission', 404);
        abort_unless($payout->status === 'pending', 422, '该提现已处理');

        $payout->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('ok', '提现已发放');
    }

    public function reject(Tenant $tenant, Payout $payout, Request $request)
    {
        abort_if($payout->tenant_id !== $tenant->id || $payout->type !== 'commission', 404);
        abort_unless($payout->status === 'pending', 422, '该提现已处理');

        DB::transaction(function () use ($payout) {
            $payout->update(['status' => 'failed', 'notes' => '驳回']);

            // 回流：被驳回的提现对应流水（提现时 settled）恢复为可提现
            $payout->ledgerRows()->where('status', 'settled')->update(['status' => 'available']);
        });

        return back()->with('ok', '提现已驳回，佣金已回流');
    }
}
