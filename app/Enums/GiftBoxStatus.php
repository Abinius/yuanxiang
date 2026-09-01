<?php

namespace App\Enums;

enum GiftBoxStatus: string
{
    case Draft = 'draft';
    case Making = 'making';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Making => '制作中',
            self::Shipped => '已发货',
            self::Delivered => '已送达',
        };
    }
}
