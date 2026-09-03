<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\MemberService;
use Illuminate\Http\Request;

/**
 * M5 会员等级（云乡民本人）：当前等级 / 滚动消费 / 升级进度 / 权益。
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {
    }

    public function index(Tenant $tenant, Request $request)
    {
        $user = $request->user();

        return view('site.my.member', [
            'tenant' => $tenant,
            'member' => $this->members->dashboard($user),
        ]);
    }
}
