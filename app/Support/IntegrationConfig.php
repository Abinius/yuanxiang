<?php

namespace App\Support;

use Yansongda\Pay\Pay;

/**
 * 后台可改的集成配置（`tenants.settings['integrations']`）→ config 覆盖，env 仍是兜底。
 *
 * 方案 A：密钥与证书路径不进 settings，永远只在 env / 服务器文件系统上。
 * IntegrationsController 的校验层直接拒绝这些字段，后台页面不出现对应输入框。
 *
 * 进程内幂等：apply() 只跑一次，避免每次读 config 都查库。
 * config 仓库是 app 级单例，新请求自动失效——保存后"下一次请求即生效"，无需重启 PHP。
 */
class IntegrationConfig
{
    /**
     * settings 内点路径 → config 点路径。以下三类刻意不在表里，各有专门处理：
     * - 密钥与证书路径：settings 里即使有也不生效（方案 A）
     * - wechat.mock：只允许 local/testing 改（见 apply()）
     * - wechat.app_id / pay.mode：一个键要落到多处、或要转成厂商认的类型（见下）
     */
    private const MAP = [
        'wechat.scope' => 'wechat.scope',
        'wechat.templates.live_notice' => 'wechat.templates.live_notice',
        'wechat.templates.content' => 'wechat.templates.content',
        'wechat.templates.harvest_notice' => 'wechat.templates.harvest_notice',
        'wechat.templates.renewal_notice' => 'wechat.templates.renewal_notice',
        'pay.mch_id' => 'pay.wechat.default.mch_id',
        'pay.notify_url' => 'pay.wechat.default.notify_url',
    ];

    /** 后台可改 mock 的环境。production 不在内：见下方 apply() 的说明。 */
    private const MOCK_ENVS = ['local', 'testing'];

    public static function apply(): void
    {
        if (config('integrations.applied')) {
            return;
        }
        config(['integrations.applied' => true]);

        $stored = Tenant::current()->settings['integrations'] ?? [];

        // mock 不跟 MAP 走：config/wechat.php 的默认 false 是上线安全兜底（漏配 WECHAT_MOCK
        // 时生产不会静默 mock）。若后台值能盖掉它，勾错一次就静默关掉真实支付，而前台毫无报错。
        // 生产环境一律由 env 说了算，后台那一栏只读。
        if (app()->environment(self::MOCK_ENVS)) {
            $mock = data_get($stored, 'wechat.mock');
            if ($mock !== null) {
                config(['wechat.mock' => (bool) $mock]);
            }
        }

        // appid 有两个消费点：登录/推送读 wechat.app_id，支付下单读 pay…mp_app_id
        // （yansongda get_wechat_type_key() 对 mp 返回 mp_app_id）。只改一处会让
        // 登录拿到的 openid 与下单的 appid 分叉，微信直接报不匹配，所以同源写两处。
        $appId = data_get($stored, 'wechat.app_id');
        if ($appId !== null && $appId !== '') {
            config(['wechat.app_id' => $appId, 'pay.wechat.default.mp_app_id' => $appId]);
        }

        // mode 必须转成 yansongda 的整型常量：它拿这个值当 URL 表下标
        // （`Wechat::URL[$config['mode'] ?? Pay::MODE_NORMAL]`，键是 0/1/2），
        // 也用它做 `Pay::MODE_SERVICE === $mode` 的严格比较。存字符串会让下单地址
        // 变成没有协议头的相对路径，且"服务商模式"永远不生效。
        $mode = data_get($stored, 'pay.mode');
        if ($mode !== null && $mode !== '') {
            config(['pay.wechat.default.mode' => $mode === 'service' ? Pay::MODE_SERVICE : Pay::MODE_NORMAL]);
        }

        foreach (self::MAP as $settingPath => $configPath) {
            $value = data_get($stored, $settingPath);
            // 空值 = 后台没填或主动清空 → 回落 env（config 保持出厂值不动）
            if ($value === null || $value === '') {
                continue;
            }

            config([$configPath => $value]);
        }
    }

    /** 后台页面用：mock 这一栏在当前环境能不能改。 */
    public static function mockEditable(): bool
    {
        return app()->environment(self::MOCK_ENVS);
    }

    /** 保存后立即按新值生效（重设幂等标记，下一次 apply() 重新读库）。 */
    public static function reset(): void
    {
        config(['integrations.applied' => null]);
    }
}
