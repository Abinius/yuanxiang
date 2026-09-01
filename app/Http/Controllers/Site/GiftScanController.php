<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GiftBox;
use App\Models\Tenant;

/**
 * 3.3 收礼人扫码落地页（公开）：亲笔签/寄语 + 「成为云乡民」转化（拉新，复用微信一键登录）。
 * GiftBox 是 TenantScoped + TenantMiddleware 先设上下文 → code 查询自动租户隔离，跨租户码 404。
 * 路由-param 位置性：Tenant 在前、string $code 在后。
 */
class GiftScanController extends Controller
{
    public function show(Tenant $tenant, string $code)
    {
        $giftBox = GiftBox::query()
            ->where('code', $code)
            ->with(['adoption.user', 'adoption.adoptable'])
            ->firstOrFail();

        $giver = $giftBox->adoption?->user;

        return view('site.gift.scan', compact('tenant', 'giftBox', 'giver'));
    }
}
