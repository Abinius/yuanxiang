<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adoption;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WeChatPayService;
use App\Tenancy\TenantContext;
use Database\Seeders\PlotSeeder;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 2.1 微信支付：JSAPI 下单、回调验签落单（幂等）、后台退款。
 *
 * WeChatPayService 打桩（makePartial：只 stub 触达微信接口的三处，
 * notifySuccess 走真实实现）；AdoptionService 状态机走真实代码 + DB。
 * 回调路径无登录、无 CSRF、无租户上下文——与微信服务器直推一致。
 */
class WeChatPayTest extends TestCase
{
    use RefreshDatabase;

    private const JSSDK = [
        'appId' => 'wx-mp-appid',
        'timeStamp' => '1700000000',
        'nonceStr' => 'n123',
        'package' => 'prepay_id=wx202608311234',
        'signType' => 'RSA',
        'paySign' => 'SIGN',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function villager(?string $openid = null): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '13800000001',
            'password' => 'secret123',
            'nickname' => '云乡民阿林',
            'role' => 'villager',
            'openid' => $openid,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => '13800000099',
            'username' => '13800000099',
            'password' => 'secret123',
            'nickname' => '商户管理员',
            'role' => UserRole::TenantAdmin->value,
        ]);
    }

    private function orderData(): array
    {
        return [
            'name' => '张三',
            'phone' => '13800000001',
            'province' => '宁夏',
            'city' => '吴忠',
            'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ];
    }

    /** 下单 → pending_payment 认养 + pending 支付单。 */
    private function makeOrder(User $user): Adoption
    {
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", $this->orderData())
            ->assertRedirect();

        return Adoption::where('adoptable_id', $plot->id)->firstOrFail();
    }

    private function mockPay(array $notify, array $jsapi = [], bool $refund = true): void
    {
        $mock = Mockery::mock(WeChatPayService::class)->makePartial();
        $mock->shouldReceive('parseNotify')->andReturn($notify);
        $mock->shouldReceive('jsapi')->andReturn($jsapi);
        if ($refund) {
            $mock->shouldReceive('requestRefund')->andReturnUsing(fn () => null);
        }

        $this->app->instance(WeChatPayService::class, $mock);
    }

    // ── 回调：支付 → 订单生效 ─────────────────────────────────

    public function test_notify_pays_order_and_advances_to_pending_agreement(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $adoption = $this->makeOrder($this->villager('mock_openid_1'));

        $this->mockPay(['out_trade_no' => $adoption->adoption_no, 'transaction_id' => 'wx-tx-001']);

        $this->postJson('/pay/wechat/notify', ['resource' => []])
            ->assertOk()
            ->assertJson(['code' => 'SUCCESS', 'message' => '成功']);

        $payment = $adoption->payments()->first();
        $this->assertSame('paid', $payment->status->value);
        $this->assertSame('wx-tx-001', $payment->transaction_id);
        $this->assertSame('wechat', $payment->method);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('pending_agreement', $adoption->fresh()->status->value);
    }

    public function test_duplicate_notify_has_no_side_effect(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $adoption = $this->makeOrder($this->villager('mock_openid_1'));

        $this->mockPay(['out_trade_no' => $adoption->adoption_no, 'transaction_id' => 'wx-tx-001']);
        $this->postJson('/pay/wechat/notify', [])->assertOk();

        $payment = $adoption->payments()->first();
        $firstTx = $payment->transaction_id;
        $firstPaidAt = $payment->paid_at;

        // 微信重试同一笔回调（换了交易号，验证不会被二次覆盖）
        $this->mockPay(['out_trade_no' => $adoption->adoption_no, 'transaction_id' => 'wx-tx-999']);
        $this->postJson('/pay/wechat/notify', [])->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status->value);
        $this->assertSame($firstTx, $payment->transaction_id);
        $this->assertEquals($firstPaidAt, $payment->paid_at);
        $this->assertSame(1, $adoption->payments()->count());
        $this->assertSame('pending_agreement', $adoption->fresh()->status->value);
    }

    public function test_notify_of_unknown_order_returns_success_without_side_effect(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $adoption = $this->makeOrder($this->villager('mock_openid_1'));

        $this->mockPay(['out_trade_no' => 'NOT-EXISTS', 'transaction_id' => 'x']);

        $this->postJson('/pay/wechat/notify', [])->assertOk()->assertJson(['code' => 'SUCCESS']);

        $this->assertDatabaseHas('payments', ['payable_id' => $adoption->id, 'status' => 'pending']);
        $this->assertDatabaseHas('adoptions', ['id' => $adoption->id, 'status' => 'pending_payment']);
    }

    // ── JSAPI 下单 ────────────────────────────────────────────

    public function test_wechat_pay_returns_jssdk_params(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager('mock_openid_1');
        $adoption = $this->makeOrder($user);
        $t = $this->tenant();

        $this->mockPay(['out_trade_no' => '', 'transaction_id' => ''], self::JSSDK);

        $this->actingAs($user)
            ->postJson("/t/{$t->slug}/adopt/order/{$adoption->id}/wechat-pay")
            ->assertOk()
            ->assertJsonStructure(['appId', 'timeStamp', 'nonceStr', 'package', 'signType', 'paySign'])
            ->assertJson(self::JSSDK);
    }

    public function test_wechat_pay_rejects_without_openid(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager(); // 未走微信登录 → 无 openid
        $adoption = $this->makeOrder($user);
        $t = $this->tenant();

        $this->mockPay(['out_trade_no' => '', 'transaction_id' => '']);

        $this->actingAs($user)
            ->postJson("/t/{$t->slug}/adopt/order/{$adoption->id}/wechat-pay")
            ->assertStatus(422);
    }

    public function test_wechat_pay_rejects_when_not_pending_payment(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->villager('mock_openid_1');
        $adoption = $this->makeOrder($user);
        $t = $this->tenant();

        // 已模拟支付 → pending_agreement，不可再下单支付
        $this->actingAs($user)->post("/t/{$t->slug}/adopt/order/{$adoption->id}/pay")->assertRedirect();

        $this->mockPay(['out_trade_no' => '', 'transaction_id' => '']);

        $this->actingAs($user)
            ->postJson("/t/{$t->slug}/adopt/order/{$adoption->id}/wechat-pay")
            ->assertStatus(422);
    }

    // ── 后台退款 ──────────────────────────────────────────────

    public function test_admin_refund_refunds_payment_and_cancels_adoption(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $adoption = $this->makeOrder($this->villager('mock_openid_1'));
        $t = $this->tenant();

        $this->mockPay(['out_trade_no' => $adoption->adoption_no, 'transaction_id' => 'wx-tx-002']);
        $this->postJson('/pay/wechat/notify', [])->assertOk();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adoptions/{$adoption->id}/refund")
            ->assertRedirect();

        $payment = $adoption->payments()->first();
        $this->assertSame('refunded', $payment->status->value);
        $this->assertNotNull($payment->refund_at);
        $this->assertSame('cancelled', $adoption->fresh()->status->value);
    }

    public function test_duplicate_refund_is_idempotent(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $adoption = $this->makeOrder($this->villager('mock_openid_1'));
        $t = $this->tenant();
        $admin = $this->admin();

        $this->mockPay(['out_trade_no' => $adoption->adoption_no, 'transaction_id' => 'wx-tx-003']);
        $this->postJson('/pay/wechat/notify', [])->assertOk();

        $this->actingAs($admin)
            ->post("/t/{$t->slug}/admin/adoptions/{$adoption->id}/refund")
            ->assertRedirect();

        $payment = $adoption->payments()->first();
        $firstRefundAt = $payment->refund_at;

        // 无已支付单可退 → 静默无副作用
        $this->actingAs($admin)
            ->post("/t/{$t->slug}/admin/adoptions/{$adoption->id}/refund")
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('refunded', $payment->status->value);
        $this->assertEquals($firstRefundAt, $payment->refund_at);
        $this->assertSame(1, $adoption->payments()->count());
        $this->assertSame('cancelled', $adoption->fresh()->status->value);
    }
}
