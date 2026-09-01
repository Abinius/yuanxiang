<?php

namespace App\Enums;

enum GiftFestival: string
{
    case Spring = 'spring';             // 春节
    case DragonBoat = 'dragon_boat';    // 端午
    case MidAutumn = 'mid_autumn';      // 中秋

    public function label(): string
    {
        return match ($this) {
            self::Spring => '春节',
            self::DragonBoat => '端午',
            self::MidAutumn => '中秋',
        };
    }
}
