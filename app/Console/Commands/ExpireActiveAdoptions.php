<?php

namespace App\Console\Commands;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use Illuminate\Console\Command;
use Throwable;

/**
 * 每日把 end_date 已过的生效中认养推进到「已到期」。
 *
 * 转移图早已声明 Active → Ended，此前却没有任何调用点：过期认养永远停在 active，
 * 于是地块永久「在约」无法删除、到期后仍持续打配送单与按 active 算欠收退费。
 */
class ExpireActiveAdoptions extends Command
{
    protected $signature = 'adoption:expire-active';

    protected $description = '把 end_date 已过的生效中认养推进到已到期';

    public function handle(): int
    {
        $expired = Adoption::query()
            ->where('status', AdoptionStatus::Active->value)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($expired as $adoption) {
            try {
                $adoption->transitionTo(AdoptionStatus::Ended);
                $count++;
            } catch (Throwable) {
                // 期间已被退款取消等，跳过即可
            }
        }

        $this->info("已将 {$count} 个认养推进到已到期");

        return self::SUCCESS;
    }
}
