<?php

namespace App\Console\Commands;

use App\Services\RenewalService;
use Illuminate\Console\Command;

/**
 * F9：每日调度 —— 到期 30/7/1 天提醒 + auto_renew 临期自动建下一季单。
 * 推送走模板消息 mock 通道（P6 后真发不改码）；建单为待支付单（真扣款待商户号）。
 */
class RenewalReminderCommand extends Command
{
    protected $signature = 'adoption:renewal-reminder';

    protected $description = '每日：到期 30/7/1 天提醒 + auto_renew 临期自动建下一季单';

    public function __construct(private readonly RenewalService $renewals)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;
        foreach ([30, 7, 1] as $days) {
            $sent += $this->renewals->sendReminders($days);
        }

        $auto = $this->renewals->autoRenewExpiring();

        $this->info("已发送 {$sent} 条续费提醒，auto_renew 自动建单 {$auto} 单");

        return self::SUCCESS;
    }
}