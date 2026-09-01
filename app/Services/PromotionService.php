<?php

namespace App\Services;

use App\Models\Adoption;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Plan;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 3.4 促销券 + 老带新推荐码。
 * 券面额来自 promotion.rule（amount 或 percent）；推荐码 = 一张 type=referral 券的 code（可复用不消耗）。
 * 续费 = 新单（season_year+1，renewed_from_id 指前单），原单保留。
 */
class PromotionService
{
    /** 校验券并返回折扣额（0 表示无优惠）。含过期/库存校验。 */
    public function discountFor(Coupon $coupon, Plan $plan, int $seasonYear): float
    {
        abort_unless($coupon->status === 'unused', 422, '该券不可用');
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            abort(422, '该券已过期');
        }

        $promotion = $coupon->promotion;
        abort_unless($promotion && $promotion->status === 'active', 422, '活动不存在或已下线');
        abort_if($promotion->starts_at && now()->lt($promotion->starts_at), 422, '活动未开始');
        abort_if($promotion->ends_at && now()->gt($promotion->ends_at), 422, '活动已结束');
        // 库存校验:stock 字段 null = 不限;>0 = 限量剩余;<=0 = 已用完
        if ($promotion->stock !== null && (int) $promotion->stock <= 0) {
            abort(422, '活动名额已满');
        }

        $rule = $promotion->rule ?? [];
        $scopePlans = $rule['scope']['plan_id'] ?? null;
        if ($scopePlans && ! in_array($plan->id, (array) $scopePlans, true)) {
            abort(422, '该券不适用此方案');
        }
        $scopeSeason = $rule['scope']['season_year'] ?? null;
        if ($scopeSeason && (int) $scopeSeason !== $seasonYear) {
            abort(422, '该券不适用此年度');
        }

        if (array_key_exists('percent', $rule)) {
            return round($plan->price_yearly * (float) $rule['percent'], 2);
        }

        $minCondition = (float) ($rule['min_condition'] ?? 0);
        abort_if($plan->price_yearly < $minCondition, 422, '未达使用门槛');

        return min((float) ($rule['amount'] ?? 0), $plan->price_yearly);
    }

    /** 记录券使用（coupon_usages）+ 券置 used + 扣减活动库存。 */
    public function recordUsage(Coupon $coupon, Adoption $adoption, float $amountOff): void
    {
        CouponUsage::create([
            'tenant_id' => $adoption->tenant_id,
            'adoption_id' => $adoption->id,
            'coupon_id' => $coupon->id,
            'amount_off' => $amountOff,
            'used_at' => now(),
        ]);
        $coupon->update(['status' => 'used', 'used_at' => now()]);

        // 扣减活动库存（限量活动才扣；库存字段为 null/0 表示不限，不扣）。
        // 用原子 SQL:WHERE stock > 0 保证不超过可用库存,WHERE affected rows = 0 表示并发已被扣完。
        // affected_rows 为 0 说明名额已被并发抢光,此时券仍扣减会超卖,故回滚本交易。
        $promo = $coupon->promotion;
        if ($promo && (int) ($promo->stock ?? 0) > 0) {
            $updated = Promotion::query()
                ->where('id', $promo->id)
                ->where('stock', '>', 0)
                ->update(['stock' => DB::raw('stock - 1')]);
            if (! $updated) {
                throw new \RuntimeException('活动名额已满');
            }
        }
    }

    /** 生成/取回用户的推荐码券（可复用）；推荐活动未配置时返回 null（页面友好降级，不 422）。 */
    public function getOrCreateReferral(User $user): ?Coupon
    {
        $existing = Coupon::query()
            ->where('user_id', $user->id)
            ->whereHas('promotion', fn ($q) => $q->where('type', 'referral'))
            ->first();
        if ($existing) {
            return $existing;
        }

        $promo = Promotion::where('tenant_id', $user->tenant_id)->where('type', 'referral')->where('status', 'active')->first();
        if (! $promo) {
            return null;
        }

        return Coupon::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'promotion_id' => $promo->id,
            'code' => 'REF'.strtoupper(Str::random(8)),
            'status' => 'unused',
            'issued_at' => now(),
        ]);
    }

    /** 新客下单填推荐码：给新客发 new_customer 券、给推荐人发 renewal 券。 */
    public function redeemReferral(string $code, User $newUser): void
    {
        $referral = Coupon::query()
            ->where('code', $code)
            ->whereHas('promotion', fn ($q) => $q->where('type', 'referral'))
            ->first();
        abort_if(! $referral || $referral->user_id === $newUser->id, 422, '推荐码无效或不可自荐');

        $referrer = $referral->user;

        $newPromo = Promotion::where('tenant_id', $newUser->tenant_id)->where('type', 'new_customer')->where('status', 'active')->first();
        $renewPromo = Promotion::where('tenant_id', $referrer->tenant_id)->where('type', 'renewal')->where('status', 'active')->first();
        abort_if(! $newPromo || ! $renewPromo, 422, '推荐奖励未配置');

        Coupon::create([
            'tenant_id' => $newUser->tenant_id, 'user_id' => $newUser->id,
            'promotion_id' => $newPromo->id, 'status' => 'unused', 'issued_at' => now(),
        ]);
        Coupon::create([
            'tenant_id' => $referrer->tenant_id, 'user_id' => $referrer->id,
            'promotion_id' => $renewPromo->id, 'status' => 'unused', 'issued_at' => now(),
        ]);
    }

    /** 续费：建下一季新单（原 plot，season_year+1，可带 renewal 券抵扣）。 */
    public function renew(User $user, Adoption $old, ?Coupon $coupon = null): Adoption
    {
        abort_unless($old->user_id === $user->id, 404);

        $plot = $old->adoptable;
        abort_if(! $plot, 422, '原认养田块不可用');

        return app(AdoptionService::class)->createOrder($user, $plot, [], [
            'season_year' => $old->season_year + 1,
            'renewed_from_id' => $old->id,
            'coupon' => $coupon,
        ]);
    }
}
