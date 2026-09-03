<?php

namespace Tests\Feature;

use App\Models\Adoption;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdoptionService;
use App\Tenancy\TenantContext;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * F2 弃付回收 + F3 起名并入成功流。
 * 待付单 72h 内可继续支付；超期回收释放田块；成功页内联命名。
 */
class AdoptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        Carbon::setTestNow(null);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function villager(string $phone = '13800000010'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);
    }

    private function makeOrder(User $user): Adoption
    {
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", [
                'name' => '张三',
                'phone' => '13800000010',
                'province' => '宁夏',
                'city' => '吴忠',
                'district' => '红寺堡',
                'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();

        return Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
    }

    // ── F2 R2.1 继续支付 + 72h 过期 ────────────────────────────

    /** 未过期 pending_payment：/my 显示「继续支付」CTA。 */
    public function test_my_shows_resume_payment_for_fresh_pending(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $this->makeOrder($user);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee('继续支付');
    }

    /** 超 72h 未付：/my 显示已过期，不再给继续支付链接。 */
    public function test_my_shows_expired_for_overdue_pending(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $this->makeOrder($user);

        Carbon::setTestNow(now()->addHours(74));

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my")
            ->assertOk()
            ->assertSee('订单已过期')
            ->assertDontSee('继续支付');
    }

    /** 超期单走 pay 页也提示过期。 */
    public function test_pay_page_shows_expired_for_overdue(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);

        Carbon::setTestNow(now()->addHours(74));

        $this->actingAs($user)
            ->get("/t/{$t->slug}/adopt/order/{$adoption->id}/pay")
            ->assertOk()
            ->assertSee('订单已过期')
            ->assertDontSee('模拟支付成功');
    }

    /** 日调度命令回收超期弃付单并释放田块。 */
    public function test_expire_pending_command_releases_plot(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();
        $this->makeOrder($user);

        Carbon::setTestNow(now()->addHours(74));

        $exit = (int) Artisan::call('adoption:expire-pending');

        $this->assertSame(0, $exit);
        $adoption = Adoption::latest()->firstOrFail();
        $this->assertSame('cancelled', $adoption->status->value);
        $this->assertSame('available', $plot->fresh()->status->value);
    }

    /** 未超期订单不被回收。 */
    public function test_expire_pending_command_skips_fresh_orders(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();
        $this->makeOrder($user);

        $exit = (int) Artisan::call('adoption:expire-pending');

        $this->assertSame(0, $exit);
        $adoption = Adoption::latest()->firstOrFail();
        $this->assertSame('pending_payment', $adoption->status->value);
    }

    // ── F3 起名并入成功流 ────────────────────────────────────

    /** pending_agreement 状态成功页内联命名卡片。 */
    public function test_success_page_shows_inline_naming_when_pending_agreement(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant()->fresh();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);
        app(AdoptionService::class)->confirmMockPayment($adoption);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/adopt/order/{$adoption->id}/success")
            ->assertOk()
            ->assertSee('待签署')
            ->assertSee('给这块田起个名字')
            ->assertSee('签署协议并命名');
    }

    /** 已签署(active)成功页直接显铭牌预览。 */
    public function test_success_page_shows_nameplate_when_active(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant()->fresh();
        $user = $this->villager();
        $adoption = $this->makeOrder($user);
        $service = app(AdoptionService::class);
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '阿林的光彩田');

        $this->actingAs($user)
            ->get("/t/{$t->slug}/adopt/order/{$adoption->id}/success")
            ->assertOk()
            ->assertSee('认养协议已签署')
            ->assertSee('阿林的光彩田')
            ->assertDontSee('给这块田起个名字');
    }
}