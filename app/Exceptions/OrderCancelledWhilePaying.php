<?php

namespace App\Exceptions;

use App\Models\Adoption;
use RuntimeException;

/**
 * 支付到账时认养单已进入终态（弃付被回收 / 已退款）。
 * 钱已扣、单已死——调用方必须发起退款，不能静默失败让微信无限重试。
 */
class OrderCancelledWhilePaying extends RuntimeException
{
    public function __construct(public readonly Adoption $adoption)
    {
        parent::__construct('支付到账时认养单已取消或已到期：'.$adoption->adoption_no);
    }
}
