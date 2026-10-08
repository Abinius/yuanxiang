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

    /** 单向链，无回退：已签收不可重发/撤回。 */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered => [],
        };
    }
}
