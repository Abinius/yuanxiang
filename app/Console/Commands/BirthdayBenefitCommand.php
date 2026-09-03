<?php

namespace App\Console\Commands;

use App\Models\Adoption;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * M5 生日权益：生日当天/当月发放权益券（生日盲盒/优惠券）。
 * 权益来源 settings.member.birthday_benefit（promotion type + 规则），未配置则跳过。
 * 复用 Coupon 体系，不引入新权益表。
 */
class BirthdayBenefitCommand extends Command
{
    protected $signature = 'member:birthday-benefit';

    protected $description = '本月生日云乡民发放生日权益券';

    public function __construct(
        private readonly SettingsService $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $month = (int) now()->format('m');
        $sent = 0;

        foreach (Tenant::where('status', 'active')->get() as $tenant) {
            $cfg = $this->settings->member($tenant)['birthday_benefit'] ?? null;
            if (! $cfg || empty($cfg['promotion_type'])) {
                continue; // 未配置权益，跳过
            }

            $promo = Promotion::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', $cfg['promotion_type'])
                ->where('status', 'active')
                ->first();
            if (! $promo) {
                continue;
            }

            User::query()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('birthday')
                ->whereMonth('birthday', $month)
                ->chunkById(200, function ($users) use ($promo, $tenant, &$sent) {
                    foreach ($users as $user) {
                        // 幂等：本月已发同类型券则跳过
                        $exists = Coupon::query()
                            ->where('tenant_id', $tenant->id)
                            ->where('user_id', $user->id)
                            ->where('promotion_id', $promo->id)
                            ->whereYear('created_at', now()->year)
                            ->exists();
                        if ($exists) {
                            continue;
                        }

                        Coupon::create([
                            'tenant_id' => $tenant->id,
                            'user_id' => $user->id,
                            'promotion_id' => $promo->id,
                            'status' => 'unused',
                            'issued_at' => now(),
                        ]);
                        $sent++;
                    }
                });
        }

        $this->info("已发放 {$sent} 张生日权益券");

        return self::SUCCESS;
    }
}
