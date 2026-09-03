<?php

namespace App\Services;

use App\Models\Adoption;
use App\Models\CommissionLedger;
use App\Models\Payout;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * M4 分销佣金：推荐人按会员 tier 比例分得佣金。
 *
 * 一切费率/门槛/冷却期读 tenants.settings（M2），零硬编码。
 *   - 会员 tier：委托 MemberService 按近 365 天消费判定；佣金 tier 最低 red
 *     （新人 level 0 也按红人算佣金，作为拉新激励），故无 'new' 档。
 *   - 佣金率：settings.commission.rates.{red,expert,partner}(%)。
 *   - 冷却期：settings.commission.cooldown_days，过后 pending→available。
 *   - 提现：扣减 available → 建 payout(type=commission, status=pending 待 admin 审核)。
 */
class CommissionService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MemberService $members,
    ) {
    }

    /** 推荐人近 365 天认养消费额（作为买家）。委托 MemberService，避免重复聚合。 */
    public function rollingSpend(User $user): float
    {
        return $this->members->rollingSpend($user);
    }

    /** 按滚动消费判定 tier（门槛来自 settings.member.tiers）；佣金最低 red。 */
    public function tierOf(User $user): string
    {
        $level = max(1, $this->members->computeLevel($user));

        return MemberService::TIER_BY_LEVEL[$level] ?? 'red';
    }

    /** 指定 tier 佣金率(%)（读 settings.commission.rates）。 */
    public function rateForTier(Tenant $tenant, string $tier): float
    {
        $rates = $this->settings->commission($tenant)['rates'] ?? [];

        return (float) ($rates[$tier] ?? 0);
    }

    /** 某用户当前 tier 对应佣金率(%)。 */
    public function rateOf(User $user): float
    {
        return $this->rateForTier($user->tenant, $this->tierOf($user));
    }

    /** 冷却期(天)。 */
    public function cooldownDays(Tenant $tenant): int
    {
        return (int) ($this->settings->commission($tenant)['cooldown_days'] ?? 7);
    }

    /**
     * 认养生效时记账：推荐人按当前 tier 拿百分比佣金。幂等。
     */
    public function credit(Adoption $adoption): ?CommissionLedger
    {
        $referredById = $adoption->referred_by_user_id;

        if (empty($referredById)) {
            return null;
        }

        return DB::transaction(function () use ($adoption, $referredById) {
            $existing = CommissionLedger::query()
                ->where('adoption_id', $adoption->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $referrer = User::where('id', $referredById)->first();
            abort_if(! $referrer || $referrer->tenant_id !== $adoption->tenant_id, 422, '推荐人不存在或跨租户');

            $tier = $this->tierOf($referrer);
            $rate = $this->rateForTier($adoption->tenant, $tier);
            $amount = round((float) $adoption->annual_fee * $rate / 100, 2);

            if ($amount <= 0) {
                return null;
            }

            return CommissionLedger::create([
                'tenant_id' => $adoption->tenant_id,
                'user_id' => $referrer->id,
                'adoption_id' => $adoption->id,
                'tier' => $tier,
                'rate' => $rate,
                'amount' => $amount,
                'status' => 'pending',
            ]);
        });
    }

    /** 认养退款/取消：冻结未结算佣金（已提现不追回，MVP 简化）。 */
    public function freezeFor(Adoption $adoption): void
    {
        CommissionLedger::query()
            ->where('adoption_id', $adoption->id)
            ->whereIn('status', ['pending', 'available'])
            ->update(['status' => 'frozen']);
    }

    /** 定时：超过冷却期的 pending → available。 */
    public function settleByCooldown(): int
    {
        $tenants = Tenant::where('status', 'active')->get();
        $count = 0;

        foreach ($tenants as $tenant) {
            $cutoff = Carbon::now()->subDays($this->cooldownDays($tenant));
            $count += (int) CommissionLedger::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->where('created_at', '<=', $cutoff)
                ->update(['status' => 'available']);
        }

        return $count;
    }

    /** 用户可提现余额。 */
    public function availableBalance(User $user): float
    {
        return (float) CommissionLedger::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', 'available')
            ->sum('amount');
    }

    /** 用户申请提现：扣减 available 流水（按序）→ 建待审 payout。 */
    public function cashOut(User $user, float $amount): Payout
    {
        return DB::transaction(function () use ($user, $amount) {
            $payout = Payout::create([
                'tenant_id' => $user->tenant_id,
                'type' => 'commission',
                'user_id' => $user->id,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            $rows = CommissionLedger::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->where('status', 'available')
                ->orderBy('created_at')
                ->get();

            $left = $amount;
            foreach ($rows as $row) {
                if ($left <= 0) {
                    break;
                }
                if ((float) $row->amount <= $left) {
                    $left -= (float) $row->amount;
                    $row->status = 'settled';
                    $row->settled_at = Carbon::now();
                    $row->payout_id = $payout->id;
                    $row->save();
                } else {
                    $row->amount = round((float) $row->amount - $left, 2);
                    $row->save();
                    $left = 0;
                }
            }

            abort_if($left > 0.009, 422, '可提现余额不足');

            return $payout;
        });
    }

    /** 用户佣金看板数据。 */
    public function ledgerFor(User $user): array
    {
        $base = CommissionLedger::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id);

        $clone = fn () => CommissionLedger::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id);

        return [
            'tier' => $this->tierOf($user),
            'rate' => $this->rateOf($user),
            'available' => $this->availableBalance($user),
            'pending' => (float) $clone()->where('status', 'pending')->sum('amount'),
            'frozen' => (float) $clone()->where('status', 'frozen')->sum('amount'),
            'settled' => (float) $clone()->where('status', 'settled')->sum('amount'),
            'items' => $base->with(['adoption'])->orderByDesc('created_at')->limit(50)->get(),
        ];
    }

    /** 推荐业绩（作为推荐人）。 */
    public function referralStats(User $user): array
    {
        $referrals = Adoption::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('referred_by_user_id', $user->id)
            ->orderByDesc('created_at');

        return [
            'total' => (int) $referrals->count(),
            'this_year' => (int) (clone $referrals)->whereYear('created_at', Carbon::now()->year)->count(),
            'revenue' => (float) (clone $referrals)->sum('annual_fee'),
            'recent' => (clone $referrals)->with(['adoptable', 'user'])->limit(10)->get(),
        ];
    }
}
