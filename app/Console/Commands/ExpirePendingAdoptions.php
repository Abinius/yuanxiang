<?php

namespace App\Console\Commands;

use App\Services\AdoptionService;
use Illuminate\Console\Command;

/**
 * F2 R2.1：日调度，回收超期未付的弃付单（pending_payment + 超 72h）。
 * 取消订单并释放田块，防止弃付单永久占用。
 */
class ExpirePendingAdoptions extends Command
{
    protected $signature = 'adoption:expire-pending';

    protected $description = '回收超过 72h 未支付的弃付单并释放田块';

    public function __construct(
        private readonly AdoptionService $adoptions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->adoptions->expirePendingOrders();

        $this->info("已回收 {$count} 个超期弃付单");

        return self::SUCCESS;
    }
}