<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待支付',
            self::Paid => '已支付',
            self::Refunded => '已退款',
        };
    }

    /** 退款即终态；退款后不重付，重付走新一季新单。 */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid],
            self::Paid => [self::Refunded],
            self::Refunded => [],
        };
    }
}
