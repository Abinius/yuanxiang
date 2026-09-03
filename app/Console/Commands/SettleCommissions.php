<?php

namespace App\Console\Commands;

use App\Services\CommissionService;
use Illuminate\Console\Command;

/**
 * M4 佣金冷却期结算：超过冷却期(settings.commission.cooldown_days)的 pending → available。
 * 冷却期后佣金才可提现，防止退款后仍付佣。
 */
class SettleCommissions extends Command
{
    protected $signature = 'commission:settle';

    protected $description = '冷却期过后将 pending 佣金转为 available';

    public function __construct(
        private readonly CommissionService $commissions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->commissions->settleByCooldown();

        $this->info("已转正 {$count} 笔佣金");

        return self::SUCCESS;
    }
}
