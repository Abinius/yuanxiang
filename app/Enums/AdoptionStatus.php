<?php

namespace App\Enums;

enum AdoptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingAgreement = 'pending_agreement';
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => '待支付',
            self::PendingAgreement => '待签约',
            self::Active => '生效中',
            self::Ended => '已到期',
            self::Cancelled => '已取消',
        };
    }
}
