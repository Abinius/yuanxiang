<?php

return [
    // 开发期模拟登录。默认 false（上线安全兜底：漏配 WECHAT_MOCK 时生产不会静默 mock）
    // 本地 dev 需显式设 WECHAT_MOCK=true 才走 mock；非 local 环境 + true 应告警
    'mock' => env('WECHAT_MOCK', false),

    'app_id' => env('WECHAT_APP_ID', ''),
    'secret' => env('WECHAT_SECRET', ''),
    'scope' => env('WECHAT_SCOPE', 'snsapi_userinfo'),

    // 2.7 模板消息：P6 服务号认证后填模板 ID 即真发（mock 模式只落库）
    'templates' => [
        'live_notice' => env('WECHAT_TEMPLATE_LIVE_NOTICE', ''),
        'content' => env('WECHAT_TEMPLATE_CONTENT', ''),
        'harvest_notice' => env('WECHAT_TEMPLATE_HARVEST_NOTICE', ''),
    ],
];
