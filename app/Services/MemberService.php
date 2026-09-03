<?php

namespace App\Services;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 会员阶梯：按近 365 天认养消费实时聚合判定等级，持久化到 users.member_level。
 *
 * 等级阈值读 tenants.settings.member.tiers（M2 已就绪，与 M4 分销 tier 复用）：
 *   - 0 新人（注册即入，无门槛）
 *   - 1 红人（满 tiers.red，默认 ¥1）
 *   - 2 达人（满 tiers.expert，默认 ¥5000，约 1 分地）
 *   - 3 合伙人（满 tiers.partner，默认 ¥30000，约 6 分地/包年大户）
 * 滚动消费不冗余存储，实时聚合 annual_fee（active/ended 认养、start_date 近 365 天）。
 */
class MemberService
{
    /** level → tier slug（与 M4 CommissionService 共用）。 */
    public const TIER_BY_LEVEL = [
        0 => 'new',
        1 => 'red',
        2 => 'expert',
        3 => 'partner',
    ];

    /** tier slug → level（反查，未知回落 0）。 */
    public const LEVEL_BY_TIER = [
        'new' => 0,
        'red' => 1,
        'expert' => 2,
        'partner' => 3,
    ];

    public function __construct(private readonly SettingsService $settings)
    {
    }

    /** 用户近 365 天认养消费额（作为买家）。 */
    public function rollingSpend(User $user): float
    {
        $cutoff = Carbon::now()->subYear()->toDateString();

        return (float) Adoption::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->whereIn('status', [AdoptionStatus::Active->value, AdoptionStatus::Ended->value])
            ->where('start_date', '>=', $cutoff)
            ->sum('annual_fee');
    }

    /** 当前消费对应的等级（0-3）。 */
    public function computeLevel(User $user): int
    {
        $tiers = $this->settings->member($user->tenant)['tiers'] ?? [];
        $spend = $this->rollingSpend($user);

        if ($spend >= (float) ($tiers['partner'] ?? PHP_INT_MAX)) {
            return 3;
        }
        if ($spend >= (float) ($tiers['expert'] ?? PHP_INT_MAX)) {
            return 2;
        }
        if ($spend >= (float) ($tiers['red'] ?? PHP_INT_MAX)) {
            return 1;
        }

        return 0;
    }

    /** 等级 → tier slug。 */
    public function tierOfLevel(int $level): string
    {
        return self::TIER_BY_LEVEL[$level] ?? 'new';
    }

    /** 用户当前 tier slug（基于实时消费，不读 member_level 字段）。 */
    public function tierOf(User $user): string
    {
        return $this->tierOfLevel($this->computeLevel($user));
    }

    /** 中文等级名。 */
    public function levelLabel(int $level): string
    {
        return match ($level) {
            3 => '合伙人',
            2 => '达人',
            1 => '红人',
            default => '新人',
        };
    }

    /**
     * 同步持久化 member_level；升级时记 member_since。降级不回退 member_since。
     * 返回是否发生等级变化（用于触发权益/通知）。
     */
    public function syncLevel(User $user): bool
    {
        $new = $this->computeLevel($user);
        $old = (int) $user->member_level;

        if ($new === $old) {
            return false;
        }

        $data = ['member_level' => $new];
        if ($new > $old || $user->member_since === null) {
            $data['member_since'] = Carbon::now();
        }

        $user->fill($data)->save();

        return true;
    }

    /** 当前等级的下一级门槛（用于升级进度展示）。最高级返回 null。 */
    public function nextThreshold(Tenant $tenant, int $level): ?float
    {
        $tiers = $this->settings->member($tenant)['tiers'] ?? [];

        return match ($level) {
            0 => (float) ($tiers['red'] ?? 1),
            1 => (float) ($tiers['expert'] ?? 5000),
            2 => (float) ($tiers['partner'] ?? 30000),
            default => null,
        };
    }

    /** 当前等级权益文案（读 settings.member.benefits，未配置则按默认权益）。 */
    public function benefits(Tenant $tenant, int $level): string
    {
        $benefits = $this->settings->member($tenant)['benefits'] ?? [];

        return $benefits[self::TIER_BY_LEVEL[$level] ?? 'new']
            ?? match ($level) {
                3 => '牧场参观 / 采收体验 / 新品首发',
                2 => '线上主题活动 / 溯源报告',
                1 => '定期优惠券',
                default => '试饮装 / 首单券',
            };
    }

    /** 等级页看板数据。 */
    public function dashboard(User $user): array
    {
        $level = $this->computeLevel($user);
        $spend = $this->rollingSpend($user);
        $next = $this->nextThreshold($user->tenant, $level);
        $persisted = (int) $user->member_level;

        return [
            'level' => $level,
            'persisted_level' => $persisted,
            'label' => $this->levelLabel($level),
            'tier' => $this->tierOfLevel($level),
            'spend' => $spend,
            'next_threshold' => $next,
            'progress' => $next > 0 ? min(1.0, $spend / $next) : 1.0,
            'benefits' => $this->benefits($user->tenant, $level),
            'member_since' => $user->member_since,
        ];
    }
}
