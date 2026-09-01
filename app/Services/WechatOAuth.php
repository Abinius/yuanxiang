<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 微信网页授权 OAuth（自研轻量，避免老包与 Laravel 12 兼容风险）。
 * 真实流程仅两步：跳转授权 → code 换 openid。开发期可用 mock 模式。
 */
class WechatOAuth
{
    public function mockEnabled(): bool
    {
        return (bool) config('wechat.mock');
    }

    /**
     * 授权跳转地址（mock 模式下不会用到）。
     */
    public function authorizeUrl(Tenant $tenant): string
    {
        $redirect = route('tenant.wechat.callback', ['tenant' => $tenant->slug]);
        $state = Str::random(16);
        session(['wechat_state' => $state]);

        return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.config('wechat.app_id')
            .'&redirect_uri='.urlencode($redirect)
            .'&response_type=code'
            .'&scope='.config('wechat.scope')
            .'&state='.$state.'#wechat_redirect';
    }

    /**
     * code 换 openid/unionid。
     */
    public function userByCode(string $code): array
    {
        $res = Http::get('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'appid' => config('wechat.app_id'),
            'secret' => config('wechat.secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ])->json();

        abort_unless(! empty($res['openid']), 422, '微信登录失败：'.($res['errmsg'] ?? '未知错误'));

        return [
            'openid' => $res['openid'],
            'unionid' => $res['unionid'] ?? null,
            'nickname' => '云乡民',
        ];
    }

    /**
     * 开发期模拟用户（WECHAT_MOCK=true 时用）。
     */
    public function mockUser(): array
    {
        return [
            'openid' => 'mock_openid_'.Str::random(8),
            'unionid' => null,
            'nickname' => '测试云乡民',
        ];
    }
}
