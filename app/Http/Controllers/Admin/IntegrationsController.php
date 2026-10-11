<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\IntegrationConfig;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yansongda\Pay\Pay;

/**
 * B5 微信与支付配置（tenant_admin）。
 *
 * 方案 A：只收"非密钥"项（appid / scope / 模板 ID / 商户号 / 模式 / 回调地址）。
 * secret、APIv3 key、证书路径一律不进 settings——校验层直接拒绝这些字段，
 * 后台页面也不出现对应输入框，它们只在 env / 服务器文件系统上。
 */
class IntegrationsController extends Controller
{
    private const TEMPLATES = ['live_notice', 'content', 'harvest_notice', 'renewal_notice'];

    /** 密钥类字段：后台永不接受（提交即 422，即使有人手搓请求）。 */
    private const PROHIBITED = [
        'wechat.secret' => '公众号 secret 属密钥，不能用后台保存；请用服务器环境变量 WECHAT_SECRET',
        'pay.mch_secret_key' => 'APIv3 密钥属密钥，不能用后台保存；请用环境变量 WECHAT_APIV3_KEY',
        'pay.mch_secret_key_v2' => '商户密钥属密钥，不能用后台保存；请用环境变量配置',
        'pay.mch_secret_cert' => '证书路径属密钥类配置，不能用后台保存；请用环境变量 WECHAT_SECRET_CERT_PATH',
        'pay.mch_public_cert_path' => '证书路径属密钥类配置，不能用后台保存；请用环境变量 WECHAT_PUBLIC_CERT_PATH',
    ];

    public function edit(): View
    {
        return view('admin.integrations.edit', ['integration' => $this->effective()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), self::PROHIBITED);

        $url = (string) ($data['pay']['notify_url'] ?? '');
        if ($url !== '') {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $appHost = (string) (parse_url(config('app.url'), PHP_URL_HOST) ?? '');
            // 只放行 https：url 规则也认 ftp/ws/gopher 等协议，光拦 'http://' 等于没拦；
            // 微信要求 https，存进去会让预下单被拒，而报错指向微信而不是这条配置。
            if (! str_starts_with(strtolower($url), 'https://') || strcasecmp($host, $appHost) !== 0) {
                throw ValidationException::withMessages([
                    'pay.notify_url' => '回调地址必须是 https，且域名与 APP_URL 一致（当前 APP_URL 主机：'.($appHost ?: '未配置').'）',
                ]);
            }
        }

        $stored = [
            'wechat' => [
                'mock' => (bool) ($data['wechat']['mock'] ?? false),
                'app_id' => trim((string) ($data['wechat']['app_id'] ?? '')),
                'scope' => (string) $data['wechat']['scope'],
                'templates' => array_fill_keys(self::TEMPLATES, ''),
            ],
            'pay' => [
                'mch_id' => trim((string) ($data['pay']['mch_id'] ?? '')),
                'mode' => (string) $data['pay']['mode'],
                'notify_url' => trim((string) ($data['pay']['notify_url'] ?? '')),
            ],
        ];
        foreach (self::TEMPLATES as $key) {
            $stored['wechat']['templates'][$key] = trim((string) ($data['wechat']['templates'][$key] ?? ''));
        }

        $tenant = Tenant::current();
        $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['integrations' => $stored])]);

        // 本请求内立即按新值回显（正常路径是"下一次请求即生效"）
        IntegrationConfig::reset();
        IntegrationConfig::apply();

        return redirect()->route('tenant.admin.integrations.edit', [])
            ->with('ok', '集成配置已保存，下一次请求即生效');
    }

    /** 当前生效值（后台填的值优先，未填回落 env）。 */
    private function effective(): array
    {
        $templates = [];
        foreach (self::TEMPLATES as $key) {
            $templates[$key] = (string) config("wechat.templates.{$key}", '');
        }

        return [
            'mock' => (bool) config('wechat.mock'),
            'app_id' => (string) config('wechat.app_id', ''),
            'scope' => (string) config('wechat.scope', 'snsapi_userinfo'),
            'templates' => $templates,
            'mch_id' => (string) config('pay.wechat.default.mch_id', ''),
            // config 里是 yansongda 的整型常量，页面用 settings 的 'normal'/'service' 词表
            'mode' => (int) config('pay.wechat.default.mode', Pay::MODE_NORMAL) === Pay::MODE_SERVICE ? 'service' : 'normal',
            'notify_url' => (string) config('pay.wechat.default.notify_url', ''),
        ];
    }

    private function rules(): array
    {
        return array_merge([
            'wechat' => ['required', 'array'],
            'wechat.mock' => ['nullable', 'boolean'],
            'wechat.app_id' => ['nullable', 'string', 'max:64'],
            'wechat.scope' => ['required', Rule::in(['snsapi_base', 'snsapi_userinfo'])],
            'wechat.templates' => ['required', 'array'],
            'pay' => ['required', 'array'],
            'pay.mch_id' => ['nullable', 'string', 'max:32'],
            'pay.mode' => ['required', Rule::in(['normal', 'service'])],
            'pay.notify_url' => ['nullable', 'url', 'max:500'],
        ], [
            'wechat.templates.live_notice' => ['nullable', 'string', 'max:64'],
            'wechat.templates.content' => ['nullable', 'string', 'max:64'],
            'wechat.templates.harvest_notice' => ['nullable', 'string', 'max:64'],
            'wechat.templates.renewal_notice' => ['nullable', 'string', 'max:64'],
            'wechat.secret' => 'prohibited',
            'pay.mch_secret_key' => 'prohibited',
            'pay.mch_secret_key_v2' => 'prohibited',
            'pay.mch_secret_cert' => 'prohibited',
            'pay.mch_public_cert_path' => 'prohibited',
        ]);
    }
}
