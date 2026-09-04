<?php

namespace App\Enums;

enum PlotStatus: string
{
    case Available = 'available';
    case Adopted = 'adopted';
    case SoldOut = 'sold_out';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Available => '可认养',
            self::Adopted => '已认养',
            self::SoldOut => '已售罄',
            self::Offline => '下架',
        };
    }
}
