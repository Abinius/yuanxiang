<?php

namespace App\Services;

use App\Models\PushMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 2.7 微信模板消息：开播提醒(live_notice) / 内容动态(content)。
 *
 * mock 模式（config('wechat.mock')）只落 push_messages(status=sent) 不真发；
 * P6 服务号认证后填 WECHAT_APP_ID/SECRET + 模板 ID 即真发，不改码。
 * 合规：推送文案禁「有机产品/有机认证」（cert_status=not_started）。
 */
class WechatTemplateService
{
    public function send(User $user, string $templateKey, array $data): void
    {
        $message = PushMessage::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'channel' => 'wechat_template',
            'template_id' => config("wechat.templates.$templateKey", ''),
            'type' => $templateKey,
            'payload' => $data,
            'status' => 'queued',
        ]);

        if (config('wechat.mock')) {
            $message->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

            return;
        }

        try {
            $accessToken = Cache::remember('wechat:access_token', now()->addHours(2), function () {
                return Http::get('https://api.weixin.qq.com/cgi-bin/token', [
                    'grant_type' => 'client_credential',
                    'appid' => config('wechat.app_id'),
                    'secret' => config('wechat.secret'),
                ])->json('access_token') ?? '';
            });

            $resp = Http::withToken($accessToken)
                ->post('https://api.weixin.qq.com/cgi-bin/message/template/send', [
                    'touser' => $user->openid,
                    'template_id' => $message->template_id,
                    'url' => $data['url'] ?? '',
                    'data' => $data['data'] ?? [],
                ]);

            $message->forceFill([
                'status' => $resp->successful() ? 'sent' : 'failed',
                'errmsg' => $resp->body(),
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $message->forceFill(['status' => 'failed', 'errmsg' => $e->getMessage()])->save();
        }
    }
}
