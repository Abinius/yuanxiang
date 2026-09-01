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
    ],
];
