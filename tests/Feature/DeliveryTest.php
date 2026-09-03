<?php

namespace Tests\Feature;

use App\Jobs\SendHarvestNoticeJob;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\FarmMember;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\PushMessage;
use App\Models\Tenant;
use App\Models\TraceCode;
use App\Models\User;
use App\Services\AdoptionService;
use App\Services\DeliveryService;
use App\Services\WechatTemplateService;
use App\Tenancy\TenantContext;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BaseSeeder;
use Database\Seeders\PlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 3.1 采收配送全链路：打单（按采收为 active 认养建单）→ 发货（运单）→ 打印 →
 * C 端配送进度 + 确认收货 → 采收通知（该 plot 认养人）。
 */
class DeliveryTest extends TestCase
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

    private function farm(): Farm
    {
        return Farm::where('tenant_id', $this->tenant()->id)->firstOrFail();
    }

    private function plot(): Plot
    {
        return Plot::where('tenant_id', $this->tenant()->id)->where('type', 'plot')->first();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
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

    /** 建 active 认养人（下单会创建地址）。 */
    private function makeActiveAdopter(?string $openid = null): User
    {
        $this->phoneCounter++;
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '1381'.str_pad((string) $this->phoneCounter, 7, '0', STR_PAD_LEFT),
            'openid' => $openid,
            'password' => 'secret123',
            'nickname' => '云乡民',
            'role' => 'villager',
        ]);

        $service = app(AdoptionService::class);
        $plot = Plot::where('tenant_id', $t->id)->where('type', 'plot')->where('status', 'available')->orderBy('id')->firstOrFail();
        $adoption = $service->createOrder($user, $plot, $this->orderData());
        $service->confirmMockPayment($adoption);
        $service->signAgreement($adoption, '云乡民的田');

        return $user;
    }

    private function makeHarvest(Plot $plot, float $kg = 15): Harvest
    {
        return Harvest::create([
            'tenant_id' => $plot->tenant_id,
            'farm_id' => $plot->farm_id,
            'plot_id' => $plot->id,
            'season_year' => (int) now()->format('Y'),
            'harvested_at' => now()->toDateString(),
            'dry_weight_kg' => $kg,
            'quality_grade' => '一级',
        ]);
    }

    /** 家人端（harvest scope）用户。 */
    private function familyHarvestUser(): User
    {
        $t = $this->tenant();
        $user = User::create([
            'tenant_id' => $t->id,
            'phone' => '13900000001',
            'password' => 'secret123',
            'nickname' => '阿叔',
            'role' => 'family',
        ]);
        FarmMember::create([
            'tenant_id' => $t->id,
            'user_id' => $user->id,
            'farm_id' => $this->farm()->id,
            'relation' => 'father',
            'permission_scope' => ['harvest'],
        ]);

        return $user;
    }

    public function test_admin_creates_deliveries_for_harvest(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter('mock_delivery_1');
        $plot = $user->adoptions()->first()->adoptable;
        $harvest = $this->makeHarvest($plot);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/deliveries", ['harvest_id' => $harvest->id])
            ->assertRedirect();

        $deliveries = Delivery::where('harvest_id', $harvest->id)->get();
        $this->assertCount(1, $deliveries);
        $this->assertEquals($user->id, $deliveries->first()->adoption->user_id);
        $this->assertNotNull($deliveries->first()->address_id);
        $this->assertSame('pending', $deliveries->first()->status->value);

        // 重复打单同一采收 → 不重复建
        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/deliveries", ['harvest_id' => $harvest->id])
            ->assertRedirect();
        $this->assertSame(1, Delivery::where('harvest_id', $harvest->id)->count());
    }

    public function test_admin_ships_delivery(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();
        $harvest = $this->makeHarvest($adoption->adoptable);
        $delivery = Delivery::create([
            'tenant_id' => $t->id,
            'adoption_id' => $adoption->id,
            'harvest_id' => $harvest->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/deliveries/{$delivery->id}/ship", [
                'tracking_no' => 'SF2026090001',
                'carrier' => '顺丰',
            ])
            ->assertRedirect();

        $delivery->refresh();
        $this->assertSame('shipped', $delivery->status->value);
        $this->assertSame('SF2026090001', $delivery->tracking_no);
        $this->assertSame('顺丰', $delivery->carrier);
        $this->assertNotNull($delivery->shipped_at);
    }

    public function test_cross_tenant_delivery_ship_404(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter();
        $adoption = $user->adoptions()->first();

        $other = Tenant::create(['slug' => 'other', 'name' => '别的村', 'status' => 'active']);
        $otherFarm = Farm::create(['tenant_id' => $other->id, 'name' => '他基地']);
        $otherPlot = Plot::create([
            'tenant_id' => $other->id, 'farm_id' => $otherFarm->id, 'type' => 'plot',
            'code' => 'X-01', 'mu_area' => 0.1, 'price_yearly' => 5000,
        ]);
        $otherHarvest = $this->makeHarvest($otherPlot);
        $otherDelivery = Delivery::create([
            'tenant_id' => $other->id,
            'adoption_id' => $adoption->id,
            'harvest_id' => $otherHarvest->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post("/t/{$t->slug}/admin/deliveries/{$otherDelivery->id}/ship", ['tracking_no' => 'X'])
            ->assertNotFound();
    }

    public function test_admin_print_shows_picking_list(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter('mock_delivery_1');
        $adoption = $user->adoptions()->first();
        $harvest = $this->makeHarvest($adoption->adoptable);
        $delivery = Delivery::create([
            'tenant_id' => $t->id,
            'adoption_id' => $adoption->id,
            'harvest_id' => $harvest->id,
            'address_id' => $user->addresses()->first()?->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get("/t/{$t->slug}/admin/deliveries/print?ids={$delivery->id}")
            ->assertOk()
            ->assertSee($adoption->adoption_no)
            ->assertSee('张三');
    }

    public function test_villager_cannot_access_admin_deliveries(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter(); // villager role

        $this->actingAs($user)
            ->get("/t/{$t->slug}/admin/deliveries")
            ->assertForbidden();
    }

    public function test_adopter_confirms_receipt_on_my_plot(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $user = $this->makeActiveAdopter('mock_delivery_1');
        $adoption = $user->adoptions()->first();
        $harvest = $this->makeHarvest($adoption->adoptable);
        $delivery = Delivery::create([
            'tenant_id' => $t->id,
            'adoption_id' => $adoption->id,
            'harvest_id' => $harvest->id,
            'status' => 'shipped',
            'tracking_no' => 'SF2026090001',
        ]);

        $this->actingAs($user)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertOk()
            ->assertSee('配送进度')
            ->assertSee('确认收货')
            ->assertSee('SF2026090001');

        $this->actingAs($user)
            ->post("/t/{$t->slug}/my/plot/{$adoption->id}/deliveries/{$delivery->id}/receive")
            ->assertRedirect();

        $this->assertSame('delivered', $delivery->fresh()->status->value);
        $this->assertNotNull($delivery->fresh()->received_at);

        // 非 owner → 404
        $other = $this->makeActiveAdopter();
        $this->actingAs($other)
            ->get("/t/{$t->slug}/my/plot/{$adoption->id}")
            ->assertNotFound();
    }

    public function test_family_harvest_dispatches_notice_job(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $t = $this->tenant();
        $user = $this->familyHarvestUser();
        Queue::fake();

        $this->actingAs($user)
            ->post("/t/{$t->slug}/family/harvest", [
                'plot_id' => $this->plot()->id,
                'season_year' => now()->year,
                'harvested_at' => now()->toDateString(),
                'dry_weight_kg' => '15',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendHarvestNoticeJob::class);
    }

    /** G5/A4：家人端录采收 → 一键联动生 pending 配送 + 每箱一溯源码；重复触发幂等。 */
    public function test_family_harvest_generates_deliveries_and_trace_codes(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class, AdminSeeder::class]);
        $t = $this->tenant();
        $adopter = $this->makeActiveAdopter('mock_a4g5_1');
        $adoption = $adopter->adoptions()->first();
        $plot = $adoption->adoptable;
        $family = $this->familyHarvestUser();
        Queue::fake();

        $this->actingAs($family)
            ->post("/t/{$t->slug}/family/harvest", [
                'plot_id' => $plot->id,
                'season_year' => now()->year,
                'harvested_at' => now()->toDateString(),
                'dry_weight_kg' => '15',
            ])
            ->assertRedirect()
            ->assertSessionHas('ok', '采收已记录，配送草稿与溯源码已生成');

        $harvest = Harvest::where('plot_id', $plot->id)->latest('id')->first();

        // 每个活跃认养人 → 1 pending 配送单
        $deliveries = Delivery::where('harvest_id', $harvest->id)->get();
        $this->assertCount(1, $deliveries);
        $this->assertSame($adoption->id, $deliveries->first()->adoption_id);
        $this->assertSame('pending', $deliveries->first()->status->value);
        $this->assertNotNull($deliveries->first()->address_id);

        // 每箱一溯源码，绑定 adoption+harvest+plot
        $trace = TraceCode::where('harvest_id', $harvest->id)->first();
        $this->assertNotNull($trace);
        $this->assertSame($adoption->id, $trace->adoption_id);
        $this->assertSame($plot->id, $trace->plot_id);
        $this->assertSame(0, $trace->scanned_count);

        // 幂等：同一 harvest 再调 createForHarvest 不重复生成
        app(DeliveryService::class)->createForHarvest($harvest);
        $this->assertSame(1, Delivery::where('harvest_id', $harvest->id)->count());
        $this->assertSame(1, TraceCode::where('harvest_id', $harvest->id)->count());
    }

    public function test_harvest_notice_job_sends_to_plot_adopters(): void
    {
        $this->seed([BaseSeeder::class, PlotSeeder::class]);
        $user = $this->makeActiveAdopter('mock_harvest_1');
        $plot = $user->adoptions()->first()->adoptable;
        $harvest = $this->makeHarvest($plot);

        (new SendHarvestNoticeJob($harvest->id))->handle(app(WechatTemplateService::class));

        $this->assertSame(1, PushMessage::count());
        $message = PushMessage::first();
        $this->assertEquals($user->id, $message->user_id);
        $this->assertSame('harvest_notice', $message->type);
        $this->assertSame('sent', $message->status);
    }
}
