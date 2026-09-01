<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待发货',
            self::Shipped => '已发货',
            self::Delivered => '已签收',
        };
    }
}
