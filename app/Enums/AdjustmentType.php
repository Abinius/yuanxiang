<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case CompensateKg = 'compensate_kg';       // 补果
    case RefundProrated = 'refund_prorated';   // 折算退费
    case Defer = 'defer';                      // 顺延

    public function label(): string
    {
        return match ($this) {
            self::CompensateKg => '补果',
            self::RefundProrated => '折算退费',
            self::Defer => '顺延',
        };
    }
}
