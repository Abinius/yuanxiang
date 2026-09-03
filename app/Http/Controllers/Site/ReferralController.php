<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\CommissionService;
use App\Services\PromotionService;
use Illuminate\Http\Request;

/**
 * M4 分销中心（云乡民本人）：推荐码 + 佣金账户 + 推荐业绩 + 提现申请。
 * 佣金率/门槛/冷却期读 settings，不硬编码。
 */
class ReferralController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotions,
        private readonly CommissionService $commissions,
    ) {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $user = $request->user();
        $coupon = $this->promotions->getOrCreateReferral($user);

        return view('site.my.referral', [
            'tenant' => $tenant,
            'coupon' => $coupon,
            'commission' => $this->commissions->ledgerFor($user),
            'stats' => $this->commissions->referralStats($user),
        ]);
    }

    public function cashOut(Tenant $tenant, Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $payout = $this->commissions->cashOut($user, (float) $data['amount']);

        return back()->with('ok', "提现申请已提交 ¥{$payout->amount}，等待管理员审核。");
    }
}
