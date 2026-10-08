<?php

namespace App\Http\Controllers\Site;

use App\Enums\AdoptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Support\Tenant;

/**
 * 公开分享落地页（外链可打开，非 owner 可见）：铭牌认养分享。
 * 只暴露 named_label / 地块 code（用户自选命名），无隐私信息。
 * 路由-param 位置性：Tenant 在前、Adoption 在后；仅 tenant 守卫（不做 owner/auth）。
 */
class ShareController extends Controller
{
    public function nameplate(Adoption $adoption)
    {
        abort_unless($adoption->status === AdoptionStatus::Active, 403, '认养未生效');
        $adoption->load('adoptable');

        $shareUrl = route('tenant.share.nameplate', ['adoption' => $adoption]);

        return view('site.nameplate.public', [
            'adoption' => $adoption,
            'shareUrl' => $shareUrl,
            'seo' => [
                'description' => ($adoption->named_label ?: '我的田').' · 认养于 '.Tenant::current()->name.'（'.$adoption->adoptable?->code.'），宁夏红寺堡生态种植，全程可溯源。',
            ],
        ]);
    }
}
