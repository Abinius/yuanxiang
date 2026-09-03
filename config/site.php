<?php

return [
    // 平台默认站点/SEO 设置；租户可通过 tenants.settings 覆盖（两层 token）。
    'defaults' => [
        'title' => '光彩云村庄',
        'description' => '宁夏红寺堡枸杞认养，云乡民在线认养一畦田，生态种植全程可溯源。',
        'keywords' => '宁夏枸杞,红寺堡,认养农业,云乡民,生态种植,溯源',
        'image' => '', // og:image 绝对 URL，租户设置填
        'brand' => ['primary' => '#B33A26', 'accent' => '#C9A227'],
        'footer_copyright' => '宁夏花乌巷食品有限公司',
        'icp_number' => '',
        'contact' => '',

        // 以下均为「锚点示意」默认值，租户可在后台设置覆盖（两层 token）。
        // 实际定价/保底须先过成本核算 + 市场验证；佣金/会员/合同条款须先过法律核。
        'pricing' => [
            'fendi_yearly' => 5000,                  // 分地档年费（锚点）
            'zhu_yearly' => 300,                     // 单株档年费（锚点）
            'trial_pack' => ['min' => 199, 'max' => 399], // 试认养体验包价位区间
            'guarantee_kg' => ['fendi' => 15, 'zhu' => 0.5], // 保底产量
        ],
        'promotion' => [
            'referral' => ['new' => 300, 'referrer' => 300], // 老带新：新人/推荐人各抵（锚点）
            'new_customer' => 300,                  // 新客立减
            'renewal' => 300,                       // 续费抵用
        ],
        'commission' => [
            'rates' => ['red' => 5, 'expert' => 7, 'partner' => 10], // 三级分销佣金率（%，≤10 合规）
            'cooldown_days' => 7,                   // 认养冷却期后佣金转可用
        ],
        'member' => [
            'tiers' => ['red' => 1, 'expert' => 5000, 'partner' => 30000], // 近365天消费门槛
        ],
        'contract' => [
            'template_version' => 'v1',              // 合同条款版本（M3 合同模块消费）
        ],
    ],
];
