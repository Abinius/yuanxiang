<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PromotionService;
use Illuminate\Http\Request;

/**
 * 3.4 我的推荐码（云乡民，登录可看）：老带新互得券。
 */
class ReferralController extends Controller
{
    public function __construct(private readonly PromotionService $promotions)
    {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $coupon = $this->promotions->getOrCreateReferral($request->user());

        return view('site.my.referral', compact('tenant', 'coupon'));
    }
}
