<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Plot;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 3.4 续费（新单下季 + 续费意向开关 + renewal 券抵扣）+ 老带新推荐码（互发券）+ 促销后台。
 */
class RenewalPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private int $phoneCounter = 0;

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function makeUser(): User
    {
        $this->phoneCounter++;
        $t = $this->tenant();

        return User::create([
            'tenant_id' => $t->id,
            'phone' => '1381'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
    }

    private function makeActiveAdopter(): User
    {
        $user = $this->makeUser();
        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001', 'province' => '宁夏',
            'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        return $user;
    }

    private function makePromotion(string $type, array $rule): Promotion
    {
        return Promotion::create([
            'tenant_id' => $this->tenant()->id,
            'name' => $type,
            'type' => $type,
            'rule' => $rule,
            'status' => 'active',
        ]);
    }

    public function test_renew_creates_next_season_order(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $old = $user->adoptions()->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$old->id}/renew")
            ->assertRedirect();

        $new = Adoption::where('renewed_from_id', $old->id)->first();
        $this->assertNotNull($new);
        $this->assertSame($old->season_year + 1, $new->season_year);
        $this->assertSame(5000, (int) $new->annual_fee);
        $this->assertSame('pending_payment', $new->status->value);
    }

    public function test_auto_renew_toggle(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $old = $user->adoptions()->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$old->id}/auto-renew")
            ->assertRedirect();
        $this->assertTrue($old->fresh()->auto_renew);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$old->id}/auto-renew")
            ->assertRedirect();
        $this->assertFalse($old->fresh()->auto_renew);
    }

    public function test_renewal_coupon_discount_applied(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $old = $user->adoptions()->first();
        $promo = $this->makePromotion('renewal', ['amount' => 300]);
        $coupon = Coupon::create([
            'tenant_id' => $t->id, 'user_id' => $user->id,
            'promotion_id' => $promo->id, 'status' => 'unused', 'issued_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$old->id}/renew")
            ->assertRedirect();

        $new = Adoption::where('renewed_from_id', $old->id)->first();
        $this->assertSame(4700, (int) $new->annual_fee); // 5000 - 300
        $this->assertSame('used', $coupon->fresh()->status);
        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->count());
    }

    public function test_referral_redeem_issues_coupons_to_both(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $referralPromo = $this->makePromotion('referral', ['new_amount' => 300, 'referrer_amount' => 300]);
        $this->makePromotion('new_customer', ['amount' => 300]);
        $this->makePromotion('renewal', ['amount' => 300]);

        $referrer = $this->makeUser();
        Coupon::create([
            'tenant_id' => $t->id, 'user_id' => $referrer->id,
            'promotion_id' => $referralPromo->id, 'code' => 'REF-TEST-001', 'status' => 'unused',
        ]);

        $newUser = $this->makeUser();
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->first();

        $this->actingAs($newUser)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", [
                'name' => '王五', 'phone' => '13800000009', 'province' => '宁夏',
                'city' => '吴忠', 'district' => '红寺堡', 'detail' => '2 号',
                'referral_code' => 'REF-TEST-001',
            ])
            ->assertRedirect();

        $this->assertSame(1, Coupon::where('user_id', $newUser->id)
            ->whereHas('promotion', fn ($q) => $q->where('type', 'new_customer'))->count());
        $this->assertSame(1, Coupon::where('user_id', $referrer->id)
            ->whereHas('promotion', fn ($q) => $q->where('type', 'renewal'))->count());
    }

    public function test_coupon_of_other_user_rejected(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeUser();
        $other = $this->makeUser();
        $promo = $this->makePromotion('renewal', ['amount' => 300]);
        $otherCoupon = Coupon::create([
            'tenant_id' => $t->id, 'user_id' => $other->id,
            'promotion_id' => $promo->id, 'status' => 'unused',
        ]);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->first();

        $this->expectException(HttpException::class);
        app(AdoptionService::class)->createOrder($user, $plot, [], ['coupon' => $otherCoupon]);
    }

    public function test_villager_forbidden_admin_promotions(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/promotions")
            ->assertForbidden();
    }

    public function test_admin_creates_promotion(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/promotions", [
                'name' => '新客立减 200',
                'type' => 'new_customer',
                'amount' => '200',
                'percent' => '',
                'stock' => '',
            ])
            ->assertRedirect();

        $promo = Promotion::where('type', 'new_customer')->first();
        $this->assertNotNull($promo);
        $this->assertSame(200, (int) $promo->rule['amount']);
    }

    public function test_referral_page_graceful_when_no_promotion_configured(): void
    {
        // 全新库（不跑 PromotionSeeder）：推荐活动未配置时页面友好降级，不再 422
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/referral")
            ->assertOk()
            ->assertSee('推荐活动暂未开启');
    }
}
