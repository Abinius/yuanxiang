<?php

namespace App\Enums;

enum FarmLogType: string
{
    case Fertilize = 'fertilize';       // 施肥
    case Weed = 'weed';                 // 除草
    case Prune = 'prune';               // 修剪
    case Harvest = 'harvest';           // 采摘
    case Inspect = 'inspect';           // 检测
    case LiveBroadcast = 'live_broadcast'; // 直播预告
    case Daily = 'daily';               // 日常
    case Explain = 'explain';           // 露脸解说（≤60s 视频 + 一句话）

    public function label(): string
    {
        return match ($this) {
            self::Fertilize => '施肥',
            self::Weed => '除草',
            self::Prune => '修剪',
            self::Harvest => '采收',
            self::Inspect => '检测',
            self::LiveBroadcast => '直播',
            self::Daily => '日常',
            self::Explain => '解说',
        };
    }
}
