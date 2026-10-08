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

    /**
     * 状态转移图：续费不原地改状态，而是建新单，故 Ended 无出边。
     * 退款/弃付到期 → Cancelled（终态）。
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::PendingAgreement, self::Cancelled],
            self::PendingAgreement => [self::Active, self::Cancelled],
            self::Active => [self::Ended, self::Cancelled],
            self::Ended => [],
            self::Cancelled => [],
        };
    }
}
