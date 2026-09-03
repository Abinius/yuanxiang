<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F8 R8.2：把入口链接里的 ?ref=CODE 存进 session，供后续下单表单预填，
 * 实现礼盒扫码 → 微信登录 → 认养下单的无缝归因。
 */
class PreserveReferralCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');
        if ($ref && strlen($ref) <= 40) {
            $request->session()->put('referral_code', $ref);
        }

        return $next($request);
    }
}