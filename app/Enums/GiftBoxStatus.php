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

    /** 草稿可直接发货（跳过制作中），亦不可从已发货回退。 */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Making, self::Shipped],
            self::Making => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered => [],
        };
    }
}
