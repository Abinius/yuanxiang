<?php

declare(strict_types=1);

use Yansongda\Pay\Pay;

/*
|--------------------------------------------------------------------------
| 微信支付 v3（yansongda/laravel-pay）
|--------------------------------------------------------------------------
|
| 2.1 真接配置：全部走 env，仓库内不落任何商户凭证。
| 商户主体 = 花乌巷食品（P1，W4 前到位）。
|   WECHAT_APP_ID         公众号 appid（与 1.5 登录共用 config/wechat.php）
|   WECHAT_MCH_ID         商户号
|   WECHAT_APIV3_KEY      APIv3 密钥
|   WECHAT_SECRET_CERT_PATH   商户私钥 apiclient_key.pem 路径
|   WECHAT_PUBLIC_CERT_PATH   商户公钥证书路径
|   WECHAT_PAY_NOTIFY_URL 支付回调地址（默认 APP_URL + /pay/wechat/notify）
|   WECHAT_PAY_MODE       normal（正式）/ service（服务商）
|
| dev 期留空即可——测试通过 mock 打桩，不触达真实接口。
*/
return [
    'wechat' => [
        'default' => [
            'mch_id' => env('WECHAT_MCH_ID', ''),
            'mch_secret_key_v2' => env('WECHAT_MCH_SECRET_KEY_V2', ''),
            'mch_secret_key' => env('WECHAT_APIV3_KEY', ''),
            'mch_secret_cert' => env('WECHAT_SECRET_CERT_PATH', ''),
            'mch_public_cert_path' => env('WECHAT_PUBLIC_CERT_PATH', ''),
            'notify_url' => env('WECHAT_PAY_NOTIFY_URL', env('APP_URL').'/pay/wechat/notify'),
            'mp_app_id' => env('WECHAT_APP_ID', ''),
            'mode' => env('WECHAT_PAY_MODE', Pay::MODE_NORMAL),
        ],
    ],
    'http' => [ // optional
        'timeout' => 5.0,
        'connect_timeout' => 5.0,
    ],
    'logger' => [
        'enable' => env('WECHAT_PAY_LOG', false),
        'file' => storage_path('logs/wechat-pay.log'),
        'level' => 'debug',
        'type' => 'daily', // optional, 可选 daily.
        'max_file' => 30,
    ],
];
