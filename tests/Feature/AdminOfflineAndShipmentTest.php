<?php

namespace Tests\Feature;

use App\Enums\AdoptionStatus;
use App\Models\Adoption;
use App\Models\Delivery;
use App\Models\GiftBox;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GiftBoxService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A1 离线开单 + A2 统一发货台。
 */
class AdminOfflineAndShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function tenant(): Tenant
    {
        return Tenant::where('slug', 'guangcai')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function villager(string $phone = '13800000080'): User
    {
        return User::create([
            'tenant_id' => $this->tenant()->id,
            'phone' => $phone,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
            'openid' => 'mock_admin',
        ]);
    }

    // ── A1 离线开单 ────────────────────────────────────────

    /** admin 离线开单：选用户+地块 → 生成待签约单（线下已收款）。 */
    public function test_admin_creates_offline_order_marked_paid(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adoptions", [
                'phone' => $user->phone,
                'plot_id' => $plot->id,
                'season_year' => now()->year,
                'named_label' => '',
            ])
            ->assertRedirect();

        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();
        $this->assertSame(AdoptionStatus::PendingAgreement->value, $adoption->status->value);
        $this->assertSame($user->id, $adoption->user_id);
        $this->assertSame(1, $adoption->payments()->where('status', 'paid')->count());
    }

    /** admin 离线开单 + 命名 → 直接生效。 */
    public function test_admin_offline_order_with_name_becomes_active(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adoptions", [
                'phone' => $user->phone,
                'plot_id' => $plot->id,
                'season_year' => now()->year,
                'named_label' => '阿林的田',
            ])
            ->assertRedirect();

        $adoption = Adoption::where('adoptable_id', $plot->id)->firstOrFail();
        $this->assertSame(AdoptionStatus::Active->value, $adoption->status->value);
        $this->assertSame('阿林的田', $adoption->named_label);
    }

    /** 未注册手机号 → 422。 */
    public function test_admin_offline_order_rejects_unknown_phone(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/adoptions", [
                'phone' => '13800009999',
                'plot_id' => $plot->id,
                'season_year' => now()->year,
            ])
            ->assertStatus(422);
    }

    // ── A2 统一发货台 ──────────────────────────────────────

    /** 工作台聚合待发配送 + 待发礼盒。 */
    public function test_shipment_workbench_lists_both_queues(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();

        // 建一个待发配送
        $harvest = Harvest::create([
            'tenant_id' => $t->id, 'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id, 'season_year' => now()->year,
            'harvested_at' => now()->toDateString(), 'dry_weight_kg' => 10,
        ]);
        // 建一个待发礼盒（草稿）
        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", [
                'name' => '张三', 'phone' => $user->phone,
                'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();
        $adoption = Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
        app(\App\Services\AdoptionService::class)->markPaid($adoption);
        $giftBox = (new GiftBoxService())->create($adoption, 'spring', (int) now()->format('Y'));

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/shipments")
            ->assertOk()
            ->assertSee('统一发货台')
            ->assertSee('待发配送')
            ->assertSee('待发礼盒');
    }

    /** 工作台统一发配送 → 状态变已发货。 */
    public function test_workbench_ships_delivery(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();
        $harvest = Harvest::create([
            'tenant_id' => $t->id, 'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id, 'season_year' => now()->year,
            'harvested_at' => now()->toDateString(), 'dry_weight_kg' => 10,
        ]);

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", [
                'name' => '张三', 'phone' => $user->phone,
                'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();
        $adoption = Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
        app(\App\Services\AdoptionService::class)->markPaid($adoption);

        $delivery = Delivery::create([
            'tenant_id' => $t->id, 'adoption_id' => $adoption->id,
            'harvest_id' => $harvest->id, 'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/shipments/deliveries/{$delivery->id}/ship", [
                'tracking_no' => 'SF0001', 'carrier' => '顺丰',
            ])
            ->assertRedirect();

        $this->assertSame('shipped', $delivery->fresh()->status->value);
        $this->assertSame('SF0001', $delivery->fresh()->tracking_no);
    }

    /** 工作台统一发礼盒（草稿直接发）→ 状态已发货。 */
    public function test_workbench_ships_gift(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->villager();
        $plot = Plot::where('type', 'plot')->first();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/adopt/{$plot->id}/order", [
                'name' => '张三', 'phone' => $user->phone,
                'province' => '宁夏', 'city' => '吴忠', 'district' => '红寺堡', 'detail' => '光彩村 1 号',
            ])
            ->assertRedirect();
        $adoption = Adoption::where('adoptable_id', $plot->id)->latest()->firstOrFail();
        app(\App\Services\AdoptionService::class)->markPaid($adoption);
        $giftBox = (new GiftBoxService())->create($adoption, 'spring', (int) now()->format('Y'));

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/shipments/gifts/{$giftBox->id}/ship", [
                'tracking_no' => 'YT0001', 'carrier' => '圆通',
            ])
            ->assertRedirect();

        $this->assertSame('shipped', $giftBox->fresh()->status->value);
        $this->assertSame('YT0001', $giftBox->fresh()->tracking_no);
    }
}