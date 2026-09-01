<?php

return [
    // 开发期模拟登录（P6 服务号/网页授权配置好前为 true）
    'mock' => env('WECHAT_MOCK', true),

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
