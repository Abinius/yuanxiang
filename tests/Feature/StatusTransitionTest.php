<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GiftBoxStatus;
use App\Enums\PaymentStatus;
use App\Models\Adoption;
use App\Models\Delivery;
use App\Models\GiftBox;
use App\Models\Harvest;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\DeliveryService;
use App\Services\GiftBoxService;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * 状态机：4 个核心实体的转移规则写在各 Status 枚举的 transitions() 里，
 * 模型经 HasStatusTransitions 统一校验并落库——非法转移 422。
 * 这里既测正向链，也测终态不可动与「重复发货」这类幂等缺口。
 */
class StatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    /** 断言该转移被拒（非法转移统一 422）。 */
    private function expectRejected(callable $transition): void
    {
        try {
            $transition();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('不可转移到', $e->getMessage());

            return;
        }

        $this->fail('预期非法转移被 422 拒绝，实际放行');
    }

    /** 走完整链路建一个 active 认养，返回 [user, adoption, plot]。 */
    private function activeAdopter(): array
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13810000001',
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')
            ->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001',
            'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ]);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '测试田');

        return [$user, $adoption->refresh(), $plot];
    }

    private function deliveryFor(Adoption $adoption, Plot $plot): Delivery
    {
        $harvest = Harvest::create([
            'tenant_id' => $plot->tenant_id,
            'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id,
            'season_year' => (int) now()->format('Y'),
            'harvested_at' => now()->toDateString(),
            'dry_weight_kg' => 10,
            'quality_grade' => '一级',
        ]);

        return Delivery::create([
            'tenant_id' => $plot->tenant_id,
            'adoption_id' => $adoption->id,
            'harvest_id' => $harvest->id,
            'status' => DeliveryStatus::Pending->value,
        ]);
    }

    /** 待支付状态下的认养（用于测取消与支付链路）。 */
    private function pendingAdoption(): Adoption
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13820000001',
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')
            ->where('status', 'available')->orderBy('id')->firstOrFail();

        return app(AdoptionService::class)->createOrder($user, $plot, [
            'name' => '张三', 'phone' => '13800000001',
            'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡',
            'detail' => '光彩村 1 号',
        ]);
    }

    /** 已支付单（payment=paid，adoption 已待签约），返回 [adoption, payment]。 */
    private function paidAdoption(): array
    {
        $adoption = $this->pendingAdoption();
        app(AdoptionService::class)->confirmMockPayment($adoption);

        return [$adoption->refresh(), $adoption->payments()->latest('id')->firstOrFail()];
    }

    /** 支付 → 待签约。 */
    public function test_adoption_pending_payment_to_pending_agreement(): void
    {
        [$adoption, $payment] = $this->paidAdoption();

        $this->assertSame(AdoptionStatus::PendingAgreement, $adoption->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);

        $this->assertTrue($adoption->canTransitionTo(AdoptionStatus::Active));
        $this->assertFalse($adoption->canTransitionTo(AdoptionStatus::PendingPayment));
    }

    /** 终态：已取消不可再动。 */
    public function test_adoption_cancelled_is_terminal(): void
    {
        $adoption = $this->pendingAdoption();

        $adoption->transitionTo(AdoptionStatus::Cancelled);
        $this->assertSame(AdoptionStatus::Cancelled, $adoption->fresh()->status);

        $this->expectRejected(fn () => $adoption->fresh()->transitionTo(AdoptionStatus::Active));
    }

    /** 待签约不可回退到待支付（否则可反复"支付"跳过签约）。 */
    public function test_adoption_cannot_go_backwards(): void
    {
        [$adoption] = $this->paidAdoption();

        $this->expectRejected(fn () => $adoption->transitionTo(AdoptionStatus::PendingPayment));
    }

    /** 到期是终态：不可回退、不可取消。 */
    public function test_adoption_ended_is_terminal(): void
    {
        [, $adoption] = $this->seedAndActiveAdopter();

        $adoption->transitionTo(AdoptionStatus::Ended);

        $this->expectRejected(fn () => $adoption->fresh()->transitionTo(AdoptionStatus::Active));
    }

    /** 支付单向链：退款后不可重付。 */
    public function test_payment_refunded_is_terminal(): void
    {
        [$adoption, $payment] = $this->paidAdoption();

        $payment->transitionTo(PaymentStatus::Refunded);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);

        $this->expectRejected(fn () => $payment->fresh()->transitionTo(PaymentStatus::Paid));
    }

    /** 配送三态链 + 回归：已发货不可重复发货（原 markShipped 无守卫）。 */
    public function test_delivery_chain_rejects_duplicate_ship(): void
    {
        [, $adoption, $plot] = $this->seedAndActiveAdopter();
        $delivery = $this->deliveryFor($adoption, $plot);
        $service = app(DeliveryService::class);

        $service->markShipped($delivery, 'SF-001', '顺丰');
        $this->assertSame(DeliveryStatus::Shipped, $delivery->fresh()->status);

        // 重复发货：原先直接覆盖 tracking_no 不报错，现在 422
        $this->expectRejected(fn () => $service->markShipped($delivery->fresh(), 'SF-002', '顺丰'));
    }

    /** 未发货不可签收（守卫已从 MyPlotController 下沉到 transitionTo）。 */
    public function test_delivery_cannot_receive_before_ship(): void
    {
        [, $adoption, $plot] = $this->seedAndActiveAdopter();
        $delivery = $this->deliveryFor($adoption, $plot);

        $this->expectRejected(fn () => app(DeliveryService::class)->markReceived($delivery));
    }

    /** 礼盒四态链 + 已送达不可回退发货。 */
    public function test_giftbox_chain_and_terminal(): void
    {
        [, $adoption] = $this->seedAndActiveAdopter();
        $box = $this->giftBoxFor($adoption);
        $service = app(GiftBoxService::class);

        $service->markMaking($box);
        $service->markShipped($box->fresh(), 'SF-003', '顺丰');
        $service->markDelivered($box->fresh());
        $this->assertSame(GiftBoxStatus::Delivered, $box->fresh()->status);

        $this->expectRejected(fn () => $service->markShipped($box->fresh(), 'SF-004'));
    }

    /** 草稿可直接发货（跳过制作中）——既有业务规则，状态图显式允许。 */
    public function test_giftbox_draft_may_ship_directly(): void
    {
        [, $adoption] = $this->seedAndActiveAdopter();
        $box = $this->giftBoxFor($adoption);

        $box->transitionTo(GiftBoxStatus::Shipped, ['shipped_at' => now()]);
        $this->assertSame(GiftBoxStatus::Shipped, $box->fresh()->status);
    }

    public function seedAndActiveAdopter(): array
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);

        return $this->activeAdopter();
    }

    private function giftBoxFor(Adoption $adoption): GiftBox
    {
        return GiftBox::create([
            'tenant_id' => $adoption->tenant_id,
            'adoption_id' => $adoption->id,
            'festival' => 'spring',
            'year' => (int) now()->format('Y'),
            'code' => 'GB-T-'.$adoption->id,
            'recipient_name' => '李四',
            'recipient_phone' => '13900000000',
            'status' => GiftBoxStatus::Draft->value,
        ]);
    }
}
