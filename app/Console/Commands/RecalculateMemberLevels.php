<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MemberService;
use Illuminate\Console\Command;

/**
 * M5 会员等级日终重算：按近 365 天消费重新判定 member_level，升级记 member_since。
 * 签约/续费时已即时 syncLevel，本命令兜底处理（漏单、跨年滚动窗口变化）。
 */
class RecalculateMemberLevels extends Command
{
    protected $signature = 'member:recalculate';

    protected $description = '按近 365 天消费重算全体用户会员等级';

    public function __construct(
        private readonly MemberService $members,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $changed = 0;
        User::query()->whereNotNull('tenant_id')->chunkById(200, function ($users) use (&$changed) {
            foreach ($users as $user) {
                if ($this->members->syncLevel($user)) {
                    $changed++;
                }
            }
        });

        $this->info("已重算会员等级，{$changed} 人变更");

        return self::SUCCESS;
    }
}
